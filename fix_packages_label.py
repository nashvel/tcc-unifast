import re

with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace '3 Specimen Signatures' with 'ID (Back-to-Back) & Specimen'
content = content.replace("'3 Specimen Signatures'", "'ID (Back-to-Back) & Specimen'")

with open('backend/tests/Feature/DocumentSubmissionPackagesTest.php', 'w', encoding='utf-8') as f:
    f.write(content)
