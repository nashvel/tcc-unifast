import re

# Fix DocumentSubmissionPackagesTest '2/4' -> '2/3'
with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("'data.progress', '2/4'", "'data.progress', '2/3'")
content = content.replace("'slots_expected', 4", "'slots_expected', 3")
with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'w', encoding='utf-8') as f:
    f.write(content)

# Fix RequirementVaultDraftFlowTest '3 Specimen Signatures' -> 'ID (Back-to-Back) & Specimen'
with open('backend/tests/Feature/RequirementVaultDraftFlowTest.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = content.replace("'3 Specimen Signatures'", "'ID (Back-to-Back) & Specimen'")
with open('backend/tests/Feature/RequirementVaultDraftFlowTest.php', 'w', encoding='utf-8') as f:
    f.write(content)
