#!/usr/bin/env bash
# Maps recent file changes to affected specs using orchestration trigger table patterns.
# Run after sessions that modify subsystems to catch stale specs.

COMMITS="${1:-5}"

echo "=== Drift Detector ==="
echo "Checking files changed in last ${COMMITS} commits..."
echo ""

declare -A PATTERN_SPEC=(
  ["src/Entity/"]="docs/specs/lead-pipeline-design.md"
  ["src/Access/"]="docs/specs/lead-pipeline-design.md"
  ["src/Domain/Pipeline/"]="docs/specs/lead-pipeline-design.md"
  ["src/Domain/Qualification/"]="docs/specs/lead-pipeline-design.md"
  ["src/Controller/Api/"]="docs/specs/lead-pipeline-design.md"
  ["src/Support/"]="docs/specs/lead-pipeline-design.md"
  ["src/Provider/"]="docs/specs/lead-pipeline-design.md"
)

changed_files=$(git diff --name-only "HEAD~${COMMITS}" HEAD 2>/dev/null)
if [[ -z "$changed_files" ]]; then
    echo "No file changes found in last ${COMMITS} commits."
    exit 0
fi

declare -A affected_specs

while IFS= read -r file; do
    for pattern in "${!PATTERN_SPEC[@]}"; do
        if [[ "$file" == ${pattern}* ]]; then
            spec="${PATTERN_SPEC[$pattern]}"
            affected_specs["$spec"]=1
            echo "  ${file} -> ${spec}"
        fi
    done
done <<< "$changed_files"

if [[ ${#affected_specs[@]} -eq 0 ]]; then
    echo "No spec-mapped files changed."
else
    echo ""
    echo "Warning: ${#affected_specs[@]} spec(s) may need review:"
    for spec in "${!affected_specs[@]}"; do
        echo "  - ${spec}"
    done
fi

echo ""
echo "=== Done ==="
exit 0
