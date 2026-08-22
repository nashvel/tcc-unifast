import re
import ast

with open('backend/tests/Feature/RequirementVaultDraftFlowTest.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove deleted tests
tests_to_remove = [
    'public function test_confirm_rejects_when_profile_name_mismatches_grantee',
    'public function test_identity_check_logs_without_promoting_drafts',
    'public function test_confirm_still_works_after_optional_liveness_log',
    'private function seedSchoolIdSlot',
]

lines = content.split('\n')
i = 0
while i < len(lines):
    line = lines[i]
    if line is None:
        i += 1
        continue
    matched = False
    for m in tests_to_remove:
        if m in line:
            brace_count = 0
            start_i = i
            found_first_brace = False
            while i < len(lines):
                if lines[i] is not None:
                    if '{' in lines[i]:
                        found_first_brace = True
                        brace_count += lines[i].count('{')
                    if '}' in lines[i]:
                        brace_count -= lines[i].count('}')
                if found_first_brace and brace_count == 0:
                    for j in range(start_i, i + 1):
                        lines[j] = None
                    break
                i += 1
            matched = True
            break
    if not matched:
        i += 1

content = '\n'.join([l for l in lines if l is not None])

# 2. Fix references to seedSchoolIdSlot
content = content.replace("        $this->seedSchoolIdSlot($student, $grantee);\n", "")
content = content.replace("        $this->seedSchoolIdSlot($student, $grantee, $descriptor);\n", "")

# 3. Fix missing slots check (test_confirm_rejects_when_any_required_slot_is_missing)
content = content.replace(
    "'submission' => 'Submit all four documents to staff before confirming. Missing: '",
    "'submission' => 'Submit all required documents to staff before confirming. Missing: '"
)

content = content.replace("""        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'school_id',
            'status' => 'draft',
        ]);""", "")

# 4. Fix draft list
content = content.replace("""        $this->assertTrue(
            $draftRows->contains(fn ($row) => ($row['slot_key'] ?? '') === 'school_id'),
            'Draft filter may also include the seeded school_id identity draft.'
        );""", "")

# 5. Update confirm progress test (test_confirm_without_liveness_promotes_drafts_and_queues_pipeline)
content = content.replace("foreach (['school_id', 'course_history', 'grade_slip', 'specimen_signatures'] as $slot) {", "foreach (['course_history', 'grade_slip', 'specimen_signatures'] as $slot) {")
content = content.replace("'4/4'", "'3/3'")
content = content.replace("['School ID', 'Course History', 'Grade Slip', 'Specimen']", "['Course History', 'Grade Slip', '3 Specimen Signatures']")
content = content.replace("['School ID', 'Course History', 'Grade Slip', 'ID (Back-to-Back) & Specimen']", "['Course History', 'Grade Slip', '3 Specimen Signatures']")


with open('backend/tests/Feature/RequirementVaultDraftFlowTest.php', 'w', encoding='utf-8') as f:
    f.write(content)
