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

## Debug a failing call

Print the exact request being sent:

```bash
wethod get-client --id=42 -vvv
```

- A 401 warning → the token is wrong/expired; the user must `wethod login`.
- A 429 warning → rate-limited; wait the suggested number of seconds.
