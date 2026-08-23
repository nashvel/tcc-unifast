# CHAPTER 4: SYSTEMS DESIGN AND ARCHITECTURE

---

## 4.1 System Context (DFD Level 0)

### Figure 4.1: Context Diagram of the UniFAST TES Grantee Management System

```
[ INSERT FIGURE 4.1: CONTEXT DIAGRAM HERE ]
```

### Description of Figure 4.1:
Figure 4.1 illustrates the Context Diagram (DFD Level 0) of the UniFAST Tertiary Education Subsidy (TES) Grantee Management System, representing the global operational boundary of the application as a single centralized software process interacting with three external entities: the Administrator, the Grantee (Student Beneficiary), and the Staff (Validator). The Administrator serves as the executive authority, inputting official Commission on Higher Education (CHED) masterlist spreadsheets and documents, defining academic batch schedules, establishing submission windows, configuring institutional retention policies, designing custom survey forms, and issuing mass announcements, while receiving generated CHED TES Billing Masterlists, Grantee Fund Distribution and Payroll Summaries, system-wide Analytics Dashboards, and immutable Security Audit Logs. Concurrently, the Staff validator accesses the grantee master directory, conducts manual biometric inspections for flagged accounts, and issues document package review decisions with specific return remarks.

In parallel, the Grantee entity represents the enrolled student beneficiary who interacts with the system by providing activation tokens, demographic and academic Know-Your-Customer (KYC) information, dual-sided School ID scans, real-time facial liveness recordings, an encrypted 6-digit Document Vault security PIN, and mandatory academic verification documents including the Certificate of Registration, Official Grade Slip, and Specimen Signatures. In response, the system provides grantees with automated account activation links, real-time identity verification alerts, document return remarks for resubmission, periodic compliance evaluation surveys, and formal grant eligibility outcome notices. Through these clearly demarcated inbound and outbound data pathways, the context diagram establishes a secure, transparent, and auditable digital boundary for the administration of tertiary education subsidies.

---

## 4.2 Detailed System Flow (DFD Level 1)

### Figure 4.2: Data Flow Diagram Level 1 of the UniFAST TES Grantee Management System

```
[ INSERT FIGURE 4.2: DFD LEVEL 1 HERE ]
```

### Description of Figure 4.2:
Figure 4.2 presents the DFD Level 1 of the UniFAST TES Grantee Management System, decomposing the global system into eight (8) interconnected major processes and eight (8) persistent data stores (D1 through D8). The operational workflow initiates with Process 1.0 (User and Access Management), which governs authentication, role-based access control, and institutional security policies stored in D1 (User Accounts) and D3 (Batch & Program Records). Concurrently, Process 2.0 (Grantee Masterlist and Batch Management) parses and validates imported CHED masterlists, populates D2 (Grantee Records), and initiates account invitation dispatches via Process 7.0 (Notification Management). Beneficiaries proceed through Process 3.0 (Grantee Onboarding and Identity Verification) to complete cryptographic token verification, masterlist KYC cross-matching, dual-side ID optical character recognition (OCR), and 128-dimensional facial liveness matching against D4 (Identity & Biometric Records), with borderline cases routed to Staff review before provisioning an encrypted 6-digit vault PIN.

Once identity is verified, students access Process 4.0 (Document Submission and Review) by entering their vault PIN to upload Course History, Grade Slips, and Specimen Signatures into D6 (Document Submissions), where automated OCR extracts academic units and grades for staff review. Approved packages transition to Process 5.0 (Eligibility Assessment), an automated evaluation pipeline that assesses active enrollment, document completeness, and academic retention thresholds from D3, logging compliance badges and risk scores into D5 (Eligibility Records). Process 6.0 (Form and Survey Management) facilitates institutional survey administration via D7, while Process 7.0 broadcasts real-time in-app alerts and transactional emails across all modules. Finally, Process 8.0 (Reporting and Audit) aggregates verified eligibility records, grantee profiles, and submission data to compile official CHED TES Billing Masterlists, distribution payrolls, and executive analytics, while recording all transaction logs, IP addresses, and state changes into D8 (Audit Logs).

---

## 4.3 Subsystem Decomposition: Grantee Onboarding & Identity Verification (DFD Level 2)

### Figure 4.3: Data Flow Diagram Level 2 — Decomposition of Process 3.0

```
[ INSERT FIGURE 4.3: DFD LEVEL 2 HERE ]
```

### Description of Figure 4.3:
Figure 4.3 exhibits the DFD Level 2 diagram, detailing the internal decomposition of Process 3.0 (Grantee Onboarding and Identity Verification) into six granular subprocesses (3.1 through 3.6) and six dedicated data stores (D1 through D6). The onboarding pipeline commences at Subprocess 3.1 (Validate Token & Initialize Account), which authenticates the grantee's one-time activation token against D4 (Identity & Biometric Records) and initial batch parameters from Process 2.0, establishing account credentials in D1 (User Accounts) under a pending status. Subprocess 3.2 (Process & Cross-Match KYC Profile) captures baseline demographic and academic information, cross-referencing submitted names and student numbers against official reference data in D2 (Grantee Records - Verified Masterlist) and program codes in D3 (Batch & Program Records) to transition the account to pending identity verification.

Subprocess 3.3 (Extract & Verify School ID Data) processes dual-sided School ID uploads using optical character recognition (OCR) to extract identification fields, verify face crop quality (threshold of 0.50 or higher), and archive reference descriptors into D4. Subprocess 3.4 (Evaluate Biometric Liveness & Face Match) conducts randomized liveness challenges, computes the 128-dimensional Euclidean distance between the live selfie and ID reference photo, and executes conditional routing: high-confidence matches (Green Zone, distance under 0.45) automatically trigger verification alerts to Process 7.0 and bypass directly to Subprocess 3.6, whereas borderline matches (Yellow/Red Zone) are routed to Subprocess 3.5 (Conduct Staff Biometric Review) for manual staff inspection and audit recording in D5. Following biometric clearance, Subprocess 3.6 (Configure Document Vault Security PIN) encrypts the grantee's 6-digit PIN into D6 (Document Vault Records), activates the user profile in D1, marks the grantee as verified in D2, and outputs verified context to Process 4.0 to unlock document submission.

---

## 4.4 System Operational Workflow (Flowchart)

### Figure 4.4: End-to-End Operational Lifecycle Flowchart

```
[ INSERT FIGURE 4.4: SYSTEM FLOWCHART HERE ]
```

### Description of Figure 4.4:
Figure 4.4 illustrates the end-to-end operational lifecycle flowchart of the UniFAST TES Grantee Management System, detailing the sequential user interactions, algorithmic decisions, and database transaction commits across seven comprehensive phases. The operational lifecycle begins with administrative batch configuration and CHED masterlist ingestion, wherein the system parses and validates student records before generating signed tokens and dispatching email invites. Beneficiaries activate their accounts, complete KYC registration, and undergo automated dual-sided School ID OCR scanning and interactive facial liveness challenges. Automated Euclidean face matching bifurcates into either instant verification (Green Zone) or staff biometric queue inspection (Yellow/Red Zone), leading to the configuration of a 6-digit Document Vault security PIN upon successful clearance.

Grantees then authenticate into the Document Vault to upload required academic requirements (Certificate of Registration, Grade Slip, and Specimen Signatures), which undergo automated OCR extraction prior to staff review in the validation queue. Non-compliant documents trigger targeted resubmission notices, while approved packages advance to the automated eligibility assessment engine to evaluate enrollment status, document completeness, and academic retention thresholds against institutional failing-subject policies. Finally, qualifying grantees are marked as eligible, automated notification notices are broadcasted, and the Administrator exports official CHED TES Billing Masterlists and Fund Distribution Payroll Reports, with every action and system event immutably recorded into system audit logs to conclude the grant cycle.
