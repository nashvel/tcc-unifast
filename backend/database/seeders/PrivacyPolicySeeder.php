<?php

namespace Database\Seeders;

use App\Models\Term;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        Term::query()
            ->where('document_type', 'privacy')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        Term::updateOrCreate(
            ['document_type' => 'privacy', 'version' => 'v1.0'],
            [
                'title' => 'PRIVACY POLICY FOR TCC-UNIFAST TES PORTAL',
                'content' => <<<'POLICY'
PRIVACY POLICY FOR TCC-UNIFAST TES PORTAL

1. SCOPE
This Privacy Policy explains how Tagoloan Community College (TCC) administers personal information through the UniFAST Tertiary Education Subsidy (TES) Portal.

2. INFORMATION WE PROCESS
The portal may process account and contact details, student and academic records, eligibility and financial-support information, submitted requirements, identity-verification records, including ID images and face-verification results, as well as security, device, and audit information generated while the portal is used.

3. PURPOSES OF PROCESSING
We process this information to administer TES applications and grants, verify identity and eligibility, validate submitted documents, maintain academic and billing records, communicate service updates, protect the portal from fraud or misuse, and comply with lawful reporting, audit, and records-management obligations.

4. LEGAL AND INSTITUTIONAL BASIS
TCC processes personal information only for legitimate institutional functions, with the appropriate consent, contractual, legal, or other lawful basis required under Republic Act No. 10173, the Data Privacy Act of 2012, and applicable UniFAST and CHED requirements.

5. ACCESS AND DISCLOSURE
Access is limited to authorized TCC personnel and authorized government or institutional partners with a legitimate need to administer, validate, audit, or report the TES program. Service providers supporting secure hosting, document processing, notifications, or system operations may process information only under TCC's instructions and appropriate safeguards. TCC does not sell personal information.

6. SECURITY AND RETENTION
The portal uses role-based access controls, audit logging, protected document storage, and other reasonable organizational and technical measures. Records are retained only for as long as required for TES administration, institutional records management, dispute handling, audit, or legal obligations, then securely disposed of or anonymized where appropriate.

7. YOUR DATA PRIVACY RIGHTS
Subject to applicable law, you may request information about the personal data TCC holds about you, request correction of inaccurate information, raise concerns about processing, or exercise other rights available under the Data Privacy Act. Requests may be submitted through TCC's official data privacy or student support channels. Identity may be verified before a request is fulfilled.

8. POLICY UPDATES AND CONTACT
TCC may update this Policy when portal operations, legal requirements, or safeguards change. The current version is published in the portal. For privacy questions or concerns, contact Tagoloan Community College through its official channels and request assistance from the designated data privacy contact.
POLICY,
                'is_active' => true,
            ],
        );
    }
}
