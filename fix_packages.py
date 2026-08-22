import re

with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Update seeders from 4 slots to 3
content = content.replace("seedPackage('STU-A', 4)", "seedPackage('STU-A', 3)")
content = content.replace("seedPackage('STU-SHOW', 4)", "seedPackage('STU-SHOW', 3)")

# Update assertions
content = content.replace("'4/4'", "'3/3'")
content = content.replace("4, $packageA['slots_submitted']", "3, $packageA['slots_submitted']")
content = content.replace("'slots_expected', 4", "'slots_expected', 3")
content = content.replace("['School ID', 'Course History', 'Grade Slip', 'Specimen']", "['Course History', 'Grade Slip', '3 Specimen Signatures']")
content = content.replace("['School ID', 'Course History', 'Grade Slip', '3 Specimen Signatures']", "['Course History', 'Grade Slip', '3 Specimen Signatures']")

# Update show assertions
content = content.replace("'data.documents.0.tab_label', 'School ID'", "'data.documents.0.tab_label', 'Course History'")
content = content.replace("'data.documents.1.tab_label', 'Course History'", "'data.documents.1.tab_label', 'Grade Slip'")
content = content.replace("'data.documents.2.tab_label', 'Grade Slip'", "'data.documents.2.tab_label', '3 Specimen Signatures'")
content = content.replace("\n            ->assertJsonPath('data.documents.3.tab_label', 'Specimen')", "")
content = content.replace("\n            ->assertJsonPath('data.documents.3.tab_label', '3 Specimen Signatures')", "")

# Remove school_id from slots map in seedPackage
old_slots = """        $slots = [
            'school_id' => 'School ID',
            'course_history' => 'Course History',
            'grade_slip' => 'Grade Slip',
            'specimen_signatures' => '3 Specimen Signatures',
        ];"""
new_slots = """        $slots = [
            'course_history' => 'Course History',
            'grade_slip' => 'Grade Slip',
            'specimen_signatures' => '3 Specimen Signatures',
        ];"""
content = content.replace(old_slots, new_slots)

with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'w', encoding='utf-8') as f:
    f.write(content)
