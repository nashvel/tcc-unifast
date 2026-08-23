import re

with open('frontend/src/modules/documents/StudentDocuments.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove 'school_id' from type unions
content = re.sub(r'"school_id"\s*\|\s*', '', content)
content = re.sub(r'\s*\|\s*"school_id"', '', content)

# Remove allSlotsFilled logic checking school_id
content = re.sub(r'\s*Boolean\(slots\.value\.school_id\)\s*&&\s*', '\n    ', content)

# Remove const schoolIdUploaded = computed(() => Boolean(slots.value.school_id));
content = re.sub(r'const schoolIdUploaded = computed\(\(\) => Boolean\(slots\.value\.school_id\)\);\n', '', content)

# Remove 'school_id' from REQUIRED_SLOTS array if it exists like that, e.g. "school_id",
content = re.sub(r'\s*"school_id",\s*\n', '\n', content)

# Remove from slot checks in handleSlot1Click etc
content = re.sub(r'\s*slots\.value\.school_id,\s*\n', '\n', content)

# Remove if (slotKey === "school_id") { ... } block
content = re.sub(r'  if \(slotKey === "school_id"\) \{.*?\n  \}\n', '', content, flags=re.DOTALL)

with open('frontend/src/modules/documents/StudentDocuments.vue', 'w', encoding='utf-8') as f:
    f.write(content)
