#!/bin/sh
set -eu

usage() {
  printf 'Usage: %s --base-url URL --keychain-service SERVICE --output-dir DIR\n' "$0" >&2
  exit 64
}

base_url=''
credential_service=''
output_dir=''
while [ "$#" -gt 0 ]; do
  case "$1" in
    --base-url) base_url=${2-}; shift 2 ;;
    --keychain-service) credential_service=${2-}; shift 2 ;;
    --output-dir) output_dir=${2-}; shift 2 ;;
    *) usage ;;
  esac
done
[ -n "$base_url" ] && [ -n "$credential_service" ] && [ -n "$output_dir" ] || usage

case "$base_url" in https://*) ;; *) printf 'Only HTTPS source URLs are accepted.\n' >&2; exit 65 ;; esac
mkdir -p "$output_dir"
output_dir=$(cd "$output_dir" && pwd -P)
temp_dir=$(mktemp -d /tmp/wg-memberpress-export.XXXXXX)
cleanup() { rm -R "$temp_dir"; }
trap cleanup EXIT HUP INT TERM

credential=$(/usr/bin/security find-generic-password -s "$credential_service" -a ssg_admin -w)
archive_service='MEMBERSHIP_SUITE_MEMBERPRESS_ARCHIVE_KEY'
if ! archive_key=$(/usr/bin/security find-generic-password -s "$archive_service" -a membership-suite -w 2>/dev/null); then
  archive_key=$(/usr/bin/openssl rand -base64 48)
  /usr/bin/security add-generic-password -s "$archive_service" -a membership-suite -w "$archive_key" -U >/dev/null
fi

fetch_collection() {
  collection=$1
  endpoint="$base_url/wp-json/mp/v1/$collection"
  /usr/bin/curl --fail --silent --show-error --user "ssg_admin:$credential" -D "$temp_dir/$collection.headers" -o "$temp_dir/$collection-1.json" "$endpoint?per_page=100&page=1"
  pages=$(/usr/bin/awk 'tolower($1)=="x-wp-totalpages:"{gsub("\r",""); print $2}' "$temp_dir/$collection.headers")
  [ -n "$pages" ] || pages=1
  current=2
  while [ "$current" -le "$pages" ]; do
    /usr/bin/curl --fail --silent --show-error --user "ssg_admin:$credential" -o "$temp_dir/$collection-$current.json" "$endpoint?per_page=100&page=$current"
    current=$((current + 1))
  done
  /usr/bin/jq -s 'add' "$temp_dir/$collection-"*.json > "$temp_dir/$collection.json"
}

for collection in memberships subscriptions transactions members; do
  fetch_collection "$collection"
done
printf '[]\n' > "$temp_dir/documents.json"
printf '[]\n' > "$temp_dir/events.json"

fetched_at=$(/bin/date -u '+%Y-%m-%dT%H:%M:%SZ')
for name in memberships subscriptions transactions members documents events; do
  /usr/bin/shasum -a 256 "$temp_dir/$name.json" | /usr/bin/awk '{print $1}' > "$temp_dir/$name.sha256"
done

/usr/bin/jq -n \
  --arg schema_version '1.0.0' \
  --arg source_system 'memberpress:seo-sharing-system.de' \
  --arg source_url "$base_url" \
  --arg fetched_at "$fetched_at" \
  --argjson memberships "$(/usr/bin/jq length "$temp_dir/memberships.json")" \
  --argjson subscriptions "$(/usr/bin/jq length "$temp_dir/subscriptions.json")" \
  --argjson transactions "$(/usr/bin/jq length "$temp_dir/transactions.json")" \
  --argjson members "$(/usr/bin/jq length "$temp_dir/members.json")" \
  --arg memberships_sha256 "$(cat "$temp_dir/memberships.sha256")" \
  --arg subscriptions_sha256 "$(cat "$temp_dir/subscriptions.sha256")" \
  --arg transactions_sha256 "$(cat "$temp_dir/transactions.sha256")" \
  --arg members_sha256 "$(cat "$temp_dir/members.sha256")" \
  '{schema_version:$schema_version,source_system:$source_system,source_url:$source_url,fetched_at:$fetched_at,transport:"authenticated-read-only-rest",database_export_complete:false,events_export_complete:false,table_prefix:null,counts:{memberships:$memberships,subscriptions:$subscriptions,transactions:$transactions,members:$members,events:0,original_documents:0},sha256:{memberships:$memberships_sha256,subscriptions:$subscriptions_sha256,transactions:$transactions_sha256,members:$members_sha256},open_findings:["database_table_prefix_not_exposed_by_rest","custom_table_columns_events_and_meta_not_exported","memberpress_pdf_originals_not_exposed_or_registered_as_media","currency_not_exposed_by_memberpress_rest"]}' > "$temp_dir/manifest.json"

/usr/bin/jq -n \
  --slurpfile t "$temp_dir/transactions.json" \
  --slurpfile s "$temp_dir/subscriptions.json" \
  --slurpfile p "$temp_dir/memberships.json" \
  '{anonymized:true,transactions:($t[0]|length),subscriptions:($s[0]|length),memberships:($p[0]|length),explicit_extension_products:([$p[0][]|select(.title|test("^Erweiterung(?:\\s|–|-)","i"))]|length),transaction_statuses:($t[0]|sort_by(.status)|group_by(.status)|map({status:.[0].status,count:length,gross_total:((map(.total|tonumber? // 0)|add)*100|round/100|tostring)})),subscription_statuses:($s[0]|sort_by(.status)|group_by(.status)|map({status:.[0].status,count:length})),transactions_with_invoice_number:([$t[0][]|select((.invoice_number // "") != "")]|length),transaction_date_min:([$t[0][].created_at]|min),transaction_date_max:([$t[0][].created_at]|max),original_documents:0}' > "$temp_dir/validation-report.json"

stamp=$(/bin/date -u '+%Y%m%dT%H%M%SZ')
archive="$output_dir/memberpress-finance-$stamp.tar.gz.enc"
plain_archive="$temp_dir/memberpress-finance-$stamp.tar.gz"
/usr/bin/tar -czf "$plain_archive" -C "$temp_dir" memberships.json subscriptions.json transactions.json members.json documents.json events.json manifest.json validation-report.json
WG_ARCHIVE_KEY=$archive_key /usr/bin/openssl enc -aes-256-cbc -pbkdf2 -salt -in "$plain_archive" -out "$archive" -pass env:WG_ARCHIVE_KEY
/usr/bin/shasum -a 256 "$archive" > "$archive.sha256"
/bin/chmod 600 "$archive" "$archive.sha256"

unset credential archive_key
printf 'Encrypted source archive: %s\n' "$archive"
printf 'SHA-256 sidecar: %s\n' "$archive.sha256"
