# Holy Face AMGS — Flowcharts, ERD, and DFD

**HTML (open in a browser, then Print → PDF):** [amgs-diagrams.html](amgs-diagrams.html) or `http://127.0.0.1:8090/diagrams.html` while the web server is running. Includes simple IPO (Input–Process–Output) for the whole system, web, mobile, and desktop.

Source of truth: the running system (PHP API + MySQL `holyface_amgs`, professor web workspace, student mobile app, registrar desktop). Roles in the database remain `admin` / `teacher` / `student`; user-facing labels are **Registrar**, **Professor**, and **Student**.

---

## 1. Overall system flowchart

```mermaid
flowchart TD
  start([User opens Holy Face AMGS]) --> choose{Who is using it?}

  choose -->|Public visitor| webPub[Promotional website]
  webPub --> readSite[Read features, strands, how it works]
  readSite --> pubChoice{Next action?}
  pubChoice -->|Student or parent| portal[Official Student/Parent Portal]
  pubChoice -->|Professor| profLogin[Professor workspace login]
  pubChoice -->|Registrar download| dlDesk[Download desktop installer]

  choose -->|Professor| profLogin
  choose -->|Student| mobile[Student mobile app]
  choose -->|Registrar| desk[Registrar desktop app]

  profLogin --> apiAuthP[POST /auth/login]
  mobile --> apiAuthS[POST /auth/login]
  desk --> apiAuthR[POST /auth/login]

  apiAuthP --> roleP{Role = teacher?}
  roleP -->|No student| sendPortal[Redirect to Student/Parent Portal]
  roleP -->|No registrar| useDesk[Use desktop app]
  roleP -->|Yes| consentP{Policies accepted?}
  consentP -->|No| acceptP[Accept Terms, Privacy, AI policy]
  consentP -->|Yes| gradebook[Professor gradebook]
  acceptP --> gradebook

  apiAuthS --> roleS{Role = student?}
  roleS -->|No| rejectM[Use web or desktop instead]
  roleS -->|Yes| consentS{Policies accepted?}
  consentS -->|No| acceptS[Accept policies plus guardian if minor]
  consentS -->|Yes| studentHome[Grades, ID, study tools, official card]
  acceptS --> studentHome

  apiAuthR --> roleR{Role = admin?}
  roleR -->|No professor| useWeb[Use professor web workspace]
  roleR -->|Yes| consentR{Policies accepted?}
  consentR -->|No| acceptR[Accept policies]
  consentR -->|Yes| registrarHome[File vault, accounts, locks, audit]
  acceptR --> registrarHome

  gradebook --> api[(AMGS REST API + MySQL)]
  studentHome --> api
  registrarHome --> api
  portal -.->|school site, not AMGS grades| schoolSite[holyface.school portal]
```

---

## 2. Web flowchart (public site + professor workspace)

```mermaid
flowchart TD
  A([Open AMGS website]) --> B{Page}

  B -->|Home / Apps / Features| C[Public pages - no student records]
  C --> D{CTA}
  D -->|Portal Login| E[Redirect to Student/Parent Portal]
  D -->|Professor login| F[teacher-portal.php]
  F --> G[Professor SPA login]

  B -->|Professor login| G
  G --> H[Probe API health :8089]
  H --> I{API online?}
  I -->|No| J[Show cannot reach API]
  I -->|Yes| K[Enter username and password]
  K --> L[POST /auth/login]
  L --> M{Role}
  M -->|student| E
  M -->|admin| N[Message: use Registrar desktop]
  M -->|teacher / professor| O{consent_complete?}
  O -->|No| P[Accept policies]
  P --> Q[POST /legal/accept]
  O -->|Yes| R[Professor workspace]

  R --> S{Module}
  S -->|Grades| T[GET /grades/grid]
  T --> U[Encode WW / PT / QA]
  U --> V{Period locked?}
  V -->|Yes| W[Read-only]
  V -->|No| X[POST /grades/batch]
  X --> Y[API computes SUM + transmutation]
  Y --> Z[GET grid with quarterly grade]
  S -->|Class list| AA[Roster from same grid]
  S -->|Reports| AB[Print / CSV]
  S -->|Submit finals| AC[POST /grades/submit-final]
  AC --> AD{All section subjects submitted?}
  AD -->|Yes| AE[Issue official_report_cards snapshot]
  AD -->|No| AF[Wait for remaining professors]
  S -->|Settings| AG[GET /admin/settings view-only]
```

---

## 3. Mobile flowchart (student app)

```mermaid
flowchart TD
  A([Open Holy Face Student]) --> B[Login]
  B --> C[POST /auth/login]
  C --> D{Role = student?}
  D -->|No| E[Block: use web or desktop]
  D -->|Yes| F{Must change password?}
  F -->|Yes| G[POST /auth/change-password]
  G --> H{Policies accepted?}
  F -->|No| H
  H -->|No| I[Scroll and accept Terms, Privacy, AI]
  I --> J[POST /legal/accept]
  J --> K[Student home]
  H -->|Yes| K

  K --> L{Screen}
  L -->|Digital ID| M[GET student profile - student number, strand, section]
  L -->|Grades| N[GET grades by subject and quarter]
  L -->|Official card| O{official_report_cards exists?}
  O -->|No| P[Show progress only]
  O -->|Yes| Q[Show locked snapshot card]
  L -->|Study tools| R[Upload notes PDF/Word/photo max 15MB]
  R --> S[POST /ai/extract-notes]
  S --> T{Tool}
  T -->|Plan| U[POST /ai/study study_plan]
  T -->|Flashcards| V[POST /ai/study flashcards]
  T -->|Quiz| W[POST /ai/study quiz]
  T -->|Chat| X[POST /ai/chat]
  U --> Y[Gemini Flash-Lite - no name, student number, photo, or contact]
  V --> Y
  W --> Y
  X --> Y
```

---

## 4. Desktop flowchart (registrar console)

```mermaid
flowchart TD
  A([Open AMGS desktop]) --> B[Registrar login]
  B --> C[POST /auth/login]
  C --> D{Role}
  D -->|teacher / professor| E[Wrong role: encode on the web]
  D -->|student| F[Wrong role: use mobile app]
  D -->|admin| G{Policies accepted?}
  G -->|No| H[Accept policies]
  H --> I[Registrar workspace]
  G -->|Yes| I

  I --> J{Module}
  J -->|Overview| K[GET /admin/overview]
  J -->|File vault| L[List / upload / download / delete registrar_files]
  L --> M[POST /admin/files max 15MB]
  J -->|Accounts| N[GET/POST/PUT /admin/users]
  N --> O[Create professor or student]
  J -->|Students| P[GET /teachers/students]
  J -->|Audit| Q[GET /admin/audit and /admin/grade-audit]
  J -->|System| R[PUT settings, weights, locks, policies]
  R --> S[POST /admin/locks]
  J -->|Backup| T[GET /admin/backup JSON snapshot]
  K --> U[(API + MySQL)]
  M --> U
  O --> U
  Q --> U
  S --> U
  T --> U
```

---

## 5. Entity-relationship diagram (core academic)

Logical ERD of tables used for enrollment and DepEd grading. `students.lrn` is shown to users as **student number**.

```mermaid
erDiagram
  roles ||--o{ users : assigns
  users ||--o| teachers : "professor profile"
  users ||--o| students : "learner profile"
  users ||--o{ consent_logs : accepts
  users ||--o{ audit_logs : acts

  strands ||--o{ students : placed_in
  school_years ||--o{ students : enrolled_year
  school_years ||--o{ class_sections : offers
  strands ||--o{ class_sections : grouped_by
  teachers ||--o{ class_sections : advises

  subject_categories ||--o{ subjects : classifies
  strands ||--o{ subjects : may_specialize
  class_sections ||--o{ class_subject_assignments : has
  subjects ||--o{ class_subject_assignments : taught_as
  teachers ||--o{ class_subject_assignments : teaches

  students ||--o{ enrollments : has
  class_sections ||--o{ enrollments : includes

  class_subject_assignments ||--|| grade_component_weights : weights
  class_subject_assignments ||--o{ grade_items : defines
  class_subject_assignments ||--o{ grades : quarterly
  class_subject_assignments ||--o{ final_grades : semester

  enrollments ||--o{ grade_components : scores
  enrollments ||--o{ grades : computed
  enrollments ||--o{ final_grades : submitted
  enrollments ||--o| official_report_cards : issued
  grade_items ||--o{ grade_components : cells

  grading_weight_profiles ||--o{ subjects : default_mix
  school_years ||--o{ grading_period_locks : lockable
  enrollments ||--o{ grade_audit_logs : change_history
  users ||--o{ grade_audit_logs : records_change
```

### Supporting stores

```mermaid
erDiagram
  users ||--o{ registrar_files : uploads
  users ||--o{ refresh_tokens : sessions
  users ||--o{ announcements : publishes
  subjects ||--o{ study_materials : materials
  users ||--o{ ai_study_sessions : anonymous_tools
  study_materials ||--o{ ai_study_sessions : source
  users ||--o{ study_plan_tasks : tasks
  users ||--o{ flashcard_decks : decks
  flashcard_decks ||--o{ flashcards : cards
  users ||--o{ quiz_attempts : quizzes
  policy_documents ||--o{ consent_logs : version
```

---

## 6. DFD Level 0 — context

```mermaid
flowchart LR
  Prof[Professor]
  Stud[Student]
  Reg[Registrar]
  Parent[Parent via school portal]
  Gemini[Gemini AI]
  SYS((Holy Face AMGS))

  Prof -->|login, WW/PT/QA scores, submit finals| SYS
  SYS -->|class grid, computed grades, lock status| Prof

  Stud -->|login, notes upload, study requests| SYS
  SYS -->|grades, digital ID, official card, study output| Stud

  Reg -->|accounts, files, locks, policies, backup| SYS
  SYS -->|directory, audit logs, vault, settings| Reg

  Parent -.->|redirect only - no AMGS grade payload| SYS
  SYS -.->|link to holyface.school portal| Parent

  SYS -->|subject notes, never name or student number| Gemini
  Gemini -->|plan, flashcards, quiz, chat text| SYS
```

---

## 7. DFD Level 1 — major processes

```mermaid
flowchart TB
  Prof[Professor]
  Stud[Student]
  Reg[Registrar]
  Gemini[Gemini AI]

  P1((1.0 Authenticate and consent))
  P2((2.0 Maintain school records))
  P3((3.0 Encode and compute grades))
  P4((4.0 View progress and study))
  P5((5.0 Issue official card))

  D1[(D1 users / roles / consent)]
  D2[(D2 strands / sections / enrollments)]
  D3[(D3 grade_items / grades / finals)]
  D4[(D4 registrar_files)]
  D5[(D5 policies / settings / locks)]
  D6[(D6 audit_logs / grade_audit_logs)]
  D7[(D7 study sessions / decks / quizzes)]

  Prof --> P1
  Stud --> P1
  Reg --> P1
  P1 --> D1
  D1 --> P1

  Reg --> P2
  P2 --> D1
  P2 --> D2
  P2 --> D4
  P2 --> D5
  P2 --> D6
  D1 --> P2
  D2 --> P2
  D4 --> P2
  D5 --> P2

  Prof --> P3
  D2 --> P3
  D5 --> P3
  P3 --> D3
  P3 --> D6
  D3 --> P3
  P3 --> P5

  Stud --> P4
  D3 --> P4
  D2 --> P4
  P4 --> D7
  P4 --> Gemini
  Gemini --> P4
  P5 --> D3
  D3 --> P4
```

---

## 8. DFD Level 2 — process 3.0 Encode and compute grades

```mermaid
flowchart TB
  Prof[Professor]
  D2[(D2 class_subject_assignments / enrollments)]
  D3[(D3 grade_items / grade_components / grades / final_grades)]
  D5[(D5 grading_period_locks / transmutation_table / weights)]
  D6[(D6 grade_audit_logs)]
  P5((5.0 Issue official card))

  P31((3.1 Load class grid))
  P32((3.2 Check period lock))
  P33((3.3 Save item scores))
  P34((3.4 Compute DepEd SUM))
  P35((3.5 Submit finals))

  Prof -->|section, subject, quarter| P31
  D2 --> P31
  D3 --> P31
  P31 -->|grid rows and columns| Prof

  Prof -->|save cell| P32
  D5 -->|is_locked| P32
  P32 -->|locked| Prof
  P32 -->|open| P33

  P33 -->|old/new score| D6
  P33 --> D3
  P33 --> P34

  D5 -->|WW/PT/QA percent, transmutation bands| P34
  P34 -->|percentage, initial, transmuted| D3
  P34 -->|quarterly grade| Prof

  Prof -->|submit final| P35
  D3 --> P35
  P35 -->|q1-q4, sem, final_rating, is_submitted| D3
  P35 -->|all subjects submitted| P5
```

**3.4 computation (DepEd Order No. 8, s. 2015)** stored in DB, not hard-coded only:

1. Percentage Score = (total score ÷ total HPS) × 100 (SUM; absent/excused skipped)
2. Weighted Score = Percentage Score × component weight
3. Initial Grade = WW + PT + QA weighted
4. Quarterly Grade = transmutation table lookup
5. Final Grade = average of semester quarters, rounded
6. Official card = snapshot when every assigned subject has submitted finals
```
