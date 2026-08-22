import re
import ast

with open('backend/app/Http/Controllers/RequirementVaultController.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove SCHOOL_ID_SLOT constant and from REQUIRED_SLOTS
content = re.sub(r'\s*private const SCHOOL_ID_SLOT = \'school_id\';\n', '', content)
content = re.sub(r'\s*self::SCHOOL_ID_SLOT,\n', '\n', content)

# 2. Update confirm method
old_confirm = """    public function confirm(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $status = $grantee->submission_status ?? 'not_submitted';
        if (in_array($status, ['docs_submitted', 'under_review', 'verified'], true)) {
            throw ValidationException::withMessages([
                'submission' => 'Requirements were already confirmed for this batch.',
            ]);
        }
        if ($status === 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Staff requested a resubmission. Resubmit only returned document(s) — use Replace then Resubmit on each returned slot.',
            ]);
        }

        $missing = $this->missingRequiredSlotLabels($studentId, $batchId);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'submission' => 'Submit all four documents to staff before confirming. Missing: '
                    .implode(', ', $missing).'.',
            ]);
        }

        $schoolId = $this->requireSlot($studentId, $batchId, self::SCHOOL_ID_SLOT);
        $this->assertSchoolIdFaceBound($grantee, $schoolId);
        $nameFlags = $this->assertNameConsistency($request, $grantee, $batchId, $schoolId);

        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->where('user_id', $request->user()->id)
            ->latest('checked_at')
            ->first();

        $identityFailed = (bool) ($check?->manual_review_required)
            || (bool) $schoolId->identity_review_required
            || ($nameFlags['gradeslip_mismatch'] ?? false);

        $grantee = $this->promoteDraftsAndQueuePipeline($request, $grantee, $batchId, $check, $identityFailed);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee),
            'identity_check' => $check ? $this->presentIdentityCheck($check) : null,
            'name_consistency' => $nameFlags,
            'submitted' => true,
        ]);
    }"""

new_confirm = """    public function confirm(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $status = $grantee->submission_status ?? 'not_submitted';
        if (in_array($status, ['docs_submitted', 'under_review', 'verified'], true)) {
            throw ValidationException::withMessages([
                'submission' => 'Requirements were already confirmed for this batch.',
            ]);
        }
        if ($status === 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Staff requested a resubmission. Resubmit only returned document(s) — use Replace then Resubmit on each returned slot.',
            ]);
        }

        $missing = $this->missingRequiredSlotLabels($studentId, $batchId);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'submission' => 'Submit all required documents to staff before confirming. Missing: '
                    .implode(', ', $missing).'.',
            ]);
        }

        $user = $request->user();
        if (! empty($user->security_pin)) {
            $request->validate([
                'pin' => ['required', 'string'],
            ], [
                'pin.required' => 'A Security PIN is required to confirm your submission.',
            ]);

            if (! \\Illuminate\\Support\\Facades\\Hash::check($request->input('pin'), $user->security_pin)) {
                throw ValidationException::withMessages([
                    'pin' => 'The provided Security PIN is incorrect.',
                ]);
            }
        }

        $identityFailed = false;

        $grantee = $this->promoteDraftsAndQueuePipeline($request, $grantee, $batchId, null, $identityFailed);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee),
            'submitted' => true,
        ]);
    }"""

content = content.replace(old_confirm, new_confirm)

# 3. Clean up storeDocument requireSlot for SCHOOL_ID
content = content.replace("        $this->requireSlot($request->user()->student_id, $batchId, self::SCHOOL_ID_SLOT);\n", "")

# 4. Clean up resubmitSlot assertSchoolIdFaceBound
old_resubmit = """        if (($grantee->submission_status ?? '') !== 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Single-slot resubmit is only available after staff requests a resubmission.',
            ]);
        }

        if ($slotKey === self::SCHOOL_ID_SLOT) {
            $this->assertSchoolIdFaceBound($grantee, $submission);
        }

        $submission->update(['status' => 'pending_review']);"""
new_resubmit = """        if (($grantee->submission_status ?? '') !== 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Single-slot resubmit is only available after staff requests a resubmission.',
            ]);
        }

        $submission->update(['status' => 'pending_review']);"""
content = content.replace(old_resubmit, new_resubmit)

# 5. Clean up missingRequiredSlotLabels array map
content = re.sub(r'\s*self::SCHOOL_ID_SLOT => \'School ID\',\n', '\n', content)

# 6. Clean up presentVaultDocument face descriptor
old_present = """    private function presentVaultDocument(DocumentSubmission $item): array
    {
        $faceDescriptor = null;
        if ($item->slot_key === self::SCHOOL_ID_SLOT) {
            try {
                $faceDescriptor = $item->face_descriptor_payload;
            } catch (\Throwable $exception) {
                report($exception);
                $faceDescriptor = null;
            }
        }

        return [
            'id' => $item->id,
            'slot_key' => $item->slot_key,
            'document_type' => $item->document_type,
            'original_name' => $item->original_name,
            'secondary_original_name' => $item->secondary_original_name,
            'status' => $item->status,
            'risk_level' => $item->risk_level,
            'face_quality_score' => $item->face_quality_score,
            'identity_review_required' => $item->identity_review_required,
            'identity_review_reason' => $item->identity_review_reason,
            'review_notes' => $item->review_notes,
            'created_at' => $item->created_at,
            'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
            // Face descriptor stays owner-scoped (student vault only). Server re-verifies matches.
            'face_descriptor' => $faceDescriptor,
        ];
    }"""
new_present = """    private function presentVaultDocument(DocumentSubmission $item): array
    {
        return [
            'id' => $item->id,
            'slot_key' => $item->slot_key,
            'document_type' => $item->document_type,
            'original_name' => $item->original_name,
            'secondary_original_name' => $item->secondary_original_name,
            'status' => $item->status,
            'risk_level' => $item->risk_level,
            'face_quality_score' => $item->face_quality_score,
            'identity_review_required' => $item->identity_review_required,
            'identity_review_reason' => $item->identity_review_reason,
            'review_notes' => $item->review_notes,
            'created_at' => $item->created_at,
            'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
        ];
    }"""
content = content.replace(old_present, new_present)


# Now use a python parser to remove the whole methods
methods_to_remove = [
    'public function validateFrontIdOcr',
    'public function storeId',
    'public function storeIdentityCheck',
    'private function assertSchoolIdFaceBound',
    'private function assertNameConsistency',
    'private function gradeSlipNameCheck',
    'private function namesLooselyMatch',
    'private function frontOcrFailMessage',
    'private function assertFrontOcrMatches'
]

lines = content.split('\n')
i = 0
while i < len(lines):
    line = lines[i]
    if line is None:
        i += 1
        continue
    matched = False
    for m in methods_to_remove:
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

with open('backend/app/Http/Controllers/RequirementVaultController.php', 'w', encoding='utf-8') as f:
    f.write(content)
