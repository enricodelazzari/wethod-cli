# Workflows

Recipes for common multi-step tasks. Always pass `--json` when a later step
needs to read a value, and parse with `jq`.

## Verify access before doing work

```bash
wethod auth
```

If "Active company" is "—", stop and ask the user to run `wethod login`.

## Discover the right command

Never guess command names. List and inspect:

```bash
wethod list
wethod list-budgets --help
```

## Find a record, then act on it

Look up a client by name, then fetch its detail:

```bash
client_id=$(wethod list-clients --json | jq -r '.[] | select(.name=="Acme") | .id')
wethod get-client --id="$client_id" --json
```

## Page through a large collection

List endpoints cap `--limit` at 100. Page until a request returns fewer rows
than the limit:

```bash
offset=0
while :; do
  page=$(wethod list-clients --limit=100 --offset="$offset" --json)
  echo "$page" | jq -c '.[]'
  count=$(echo "$page" | jq 'length')
  [ "$count" -lt 100 ] && break
  offset=$((offset + 100))
done
```

## Create a record

Prefer `--field` for a few scalars, `--input` for anything nested:

```bash
wethod create-budget-area --field name="Production" --field budget_id=5 --json
wethod approve-budget --id=123 --input='{"comment":"Looks good"}'
```

## Switch between companies

Each company has its own stored token. Switch the default with `wethod login`
(re-run for the other company — it marks the latest active), or override per
command:

```bash
WETHOD_COMPANY=other-co wethod list-clients --json
```

## Fill timesheets from the weekly planning

Fetch allocations for every day of the current work week, resolve project
names, and create timesheets — optionally skipping unwanted projects (e.g. a
generic "Buffer" catch-all).

```bash
# 1. Who am I?
person_id=$(wethod get-authenticated-person --json | jq '.id')

# 2. Compute Mon–Fri of the current week (ISO weeks start on Monday)
monday=$(date -v-$(date +%u)d +%Y-%m-%d 2>/dev/null \
  || date -d "last monday" +%Y-%m-%d)   # macOS || Linux fallback
days=()
for i in 0 1 2 3 4; do
  days+=("$(date -j -v+"$i"d -f "%Y-%m-%d" "$monday" +%Y-%m-%d 2>/dev/null \
    || date -d "$monday + $i days" +%Y-%m-%d)")
done

# 3. Collect allocations, skip projects by name
SKIP_PROJECTS=("Internal Buffer")   # add any project names to exclude

for day in "${days[@]}"; do
  allocs=$(wethod list-people-allocations \
    --person-id="$person_id" --date="$day" --json)

  echo "$allocs" | jq -c '.[]' | while read -r alloc; do
    project_id=$(echo "$alloc" | jq '.project_id')
    hours=$(echo "$alloc"      | jq '.hours')
    project_name=$(wethod get-project --id="$project_id" --json | jq -r '.name')

    # Skip excluded projects
    skip=false
    for excl in "${SKIP_PROJECTS[@]}"; do
      [[ "$project_name" == "$excl" ]] && skip=true && break
    done
    $skip && echo "Skipping $project_name on $day" && continue

    wethod create-timesheet \
      --input="{\"project_id\":$project_id,\"person_id\":$person_id,\
\"date\":\"$day\",\"hours\":$hours,\"mode\":\"DAILY\"}" --json
    echo "Created timesheet: $project_name — $day — ${hours}h"
  done
done
```

**Notes**:
- The `date` command syntax differs between macOS and Linux; the snippet
  includes both variants.
- Populate `SKIP_PROJECTS` with the exact project names the user wants to
  exclude (e.g. internal buffer or overhead projects).
- Run `wethod list-timesheets --date=<day> --person-id=<id> --json` first if
  you want to check for existing timesheets before creating new ones.

## Summarize planned hours for the week (one authoritative pass)

When the user asks "how many hours did I plan this week" (or per project),
produce a **single canonical aggregation** and report from it — do not eyeball
the raw rows and narrate per-day attributions by hand. Building the total,
the per-day breakdown, and the per-project breakdown in one `jq` pass keeps the
numbers self-consistent and avoids misattributing hours to the wrong day or
project.

```bash
person_id=$(wethod get-authenticated-person --json | jq '.id')

# Mon–Fri of the current week (weeks run Mon→Fri; see SKILL.md)
monday=$(date -v-$(( $(date +%u) - 1 ))d +%Y-%m-%d 2>/dev/null \
  || date -d "last monday" +%Y-%m-%d)
friday=$(date -j -f "%Y-%m-%d" -v+4d "$monday" +%Y-%m-%d 2>/dev/null \
  || date -d "$monday +4 days" +%Y-%m-%d)

# Collect every allocation for the person (page until short), filter to the week
allocs=$(offset=0; out="[]"
  while :; do
    page=$(wethod list-people-allocations \
      --person-id="$person_id" --limit=100 --offset="$offset" --json)
    out=$(jq -s '.[0]+.[1]' <(echo "$out") <(echo "$page"))
    [ "$(echo "$page" | jq 'length')" -lt 100 ] && break
    offset=$((offset + 100))
  done
  echo "$out" | jq --arg a "$monday" --arg b "$friday" \
    'map(select(.date >= $a and .date <= $b))')

# Resolve project names into a {id: name} map
names=$(for pid in $(echo "$allocs" | jq '[.[].project_id] | unique | .[]'); do
    jq -n --arg id "$pid" \
      --arg name "$(wethod get-project --id="$pid" --json | jq -r '.name')" \
      '{($id): $name}'
  done | jq -s 'add // {}')

# One authoritative aggregation: total + by-day + by-project
jq -n --argjson allocs "$allocs" --argjson names "$names" '
  ($allocs | map(. + {project: ($names[(.project_id|tostring)] // "?")})) as $a
  | {
      total_hours: ($a | map(.hours) | add // 0),
      by_day:     ($a | group_by(.date)
                     | map({date: .[0].date,
                            hours: (map(.hours)|add),
                            projects: (map(.project)|unique)})),
      by_project: ($a | group_by(.project)
                     | map({project: .[0].project, hours: (map(.hours)|add)})
                     | sort_by(-.hours))
    }'
```

Report the table straight from this JSON. For "hours on Sense", read the
matching rows out of `by_project` instead of re-summing by hand.

## Hours on a specific project this week (filter by name keyword)

Build on the weekly planning recipe above, then filter by a case-insensitive
keyword (e.g. "sense"). Do **not** search by project ID upfront — the user may
have allocations across multiple projects whose names share the same keyword.

```bash
# 1. Get person ID
person_id=$(wethod get-authenticated-person --json | jq '.id')

# 2. Fetch allocations Mon–Fri of THIS week
monday=$(date -v-$(( $(date +%u) - 1 ))d +%Y-%m-%d 2>/dev/null \
  || date -d "last monday" +%Y-%m-%d)

allocs=$(for i in 0 1 2 3 4; do
  day=$(date -j -f "%Y-%m-%d" -v+${i}d "$monday" +%Y-%m-%d 2>/dev/null \
    || date -d "$monday +${i} days" +%Y-%m-%d)
  wethod list-people-allocations --person-id="$person_id" --date="$day" --json
done | jq -s 'flatten')

# 3. Resolve unique project names
declare -A proj_names
for pid in $(echo "$allocs" | jq '[.[].project_id] | unique | .[]'); do
  proj_names[$pid]=$(wethod get-project --id="$pid" --json | jq -r '.name')
done

# 4. Filter by keyword and print table
keyword="acme"   # replace with user's keyword
echo "| Giorno | Progetto | Ore |"
echo "|--------|----------|-----|"
matched="[]"
while read -r day pid hours; do
  name="${proj_names[$pid]}"
  if echo "$name" | grep -qi "$keyword"; then
    echo "| $day | $name | ${hours}h |"
    matched=$(jq -c --argjson h "$hours" '. + [$h]' <<<"$matched")
  fi
done < <(echo "$allocs" | jq -r '.[] | "\(.date) \(.project_id) \(.hours)"')
# Sum in jq so fractional timesheet hours are preserved (see SKILL.md quirks)
total=$(jq 'add // 0' <<<"$matched")
echo "Totale ore su '$keyword': ${total}h"
```

**Why this approach:** `list-people-allocations` has no name-search parameter.
A user can have hours split across multiple projects sharing the same keyword
(e.g. "Acme | platform 2026" and "Acme platform maintenance"), so pre-filtering
by a single project ID would return incomplete results. Always fetch all
allocations for the week first, then filter client-side.

## Debug a failing call

Print the exact request being sent:

```bash
wethod get-client --id=42 -vvv
```

- A 401 warning → the token is wrong/expired; the user must `wethod login`.
- A 429 warning → rate-limited; wait the suggested number of seconds.
