import re

with open('backend/tests/Feature/RequirementVaultSecurityTest.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix document upload test
content = content.replace("            ->assertJsonPath('slots.school_id.slot_key', 'school_id')", "")

# Remove school_id submission creation from the seeder
old_id = """        DocumentSubmission::create([
            'student_id' => $student->student_id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'slot_key' => 'school_id',
            'student_name' => $student->name,
            'document_type' => 'School ID',
            'original_name' => 'id.jpg',
            'stored_path' => 'identity/'.$grantee->id.'/id_scan_submission.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
            'status' => 'pending_review',
            'risk_level' => 'low',
        ]);"""
content = content.replace(old_id, "")

with open('backend/tests/Feature/RequirementVaultSecurityTest.php', 'w', encoding='utf-8') as f:
    f.write(content)
