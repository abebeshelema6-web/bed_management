/* Offline, multilingual help for the Kuyu HBMS user interface. */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('systemChat');
    if (!root) return;

    const panel = root.querySelector('#systemChatPanel');
    const toggle = root.querySelector('[data-chat-toggle]');
    const close = root.querySelector('[data-chat-close]');
    const languageSelect = root.querySelector('[data-chat-language]');
    const messages = root.querySelector('[data-chat-messages]');
    const suggestions = root.querySelector('[data-chat-suggestions]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');

    const text = {
        en: {
            title: 'System Help',
            subtitle: 'Offline help in your language',
            toggle: 'Help',
            close: 'Close chat',
            languageLabel: 'Chat language',
            inputLabel: 'Ask about the system',
            placeholder: 'Type your question…',
            send: 'Send message',
            greeting: 'Hello! I can help you use the hospital system. Choose a topic below or ask in English, አማርኛ, or Afaan Oromoo.',
            suggestions: [
                ['workflow', 'Full patient workflow'],
                ['patient_register', 'Register a patient'],
                ['admission', 'Create an admission'],
                ['allocation', 'Allocate a bed'],
                ['transfer', 'Transfer a patient'],
                ['discharge', 'Discharge a patient'],
                ['bed_release', 'Release a bed']
            ],
            answers: {
                workflow: 'Typical patient journey: (1) Register the patient once in Patients > Register Patient; the system assigns a patient number. (2) In Admissions > New Admission, select that patient, department, admission type, and any optional doctor/reason; save to create an ACTIVE admission. (3) An Administrator or Manager opens Allocate Bed, selects the active admission with no current bed, picks an AVAILABLE bed, and confirms; it becomes OCCUPIED. (4) If a different bed is needed, an Administrator/Manager, or the patient’s assigned Nurse, uses Transfers, selects an AVAILABLE destination and enters a reason. The new bed becomes OCCUPIED and the old bed becomes CLEANING; the admission remains active. (5) An authorized Administrator, Manager, Reception, or Doctor discharges the active admission according to hospital policy. The admission closes, its allocation closes, and its bed becomes CLEANING. (6) After staff finish cleaning, an Administrator, Manager, or Nurse changes the bed from CLEANING to AVAILABLE and enters a reason. Only then can it be allocated again. The patient record remains for future admissions. Never create duplicate patient records just because someone is readmitted.',
                permissions: 'Administrator: full access. Manager: operational management, including wards and rooms; departments are view-only, and user/role management is restricted. Doctor: patient/admission views and clinical updates. Nurse: assigned-patient access and permitted bed/transfer actions. Reception: patient registration/editing and permitted admissions/discharges. Access to sensitive actions depends on role and hospital policy.',
                patient_register: 'Who can: Administrator, Reception, and Manager. Steps: (1) Open Patients > Register Patient. (2) Enter full name, gender, and date of birth (required); phone, address, and emergency-contact details are optional. (3) Select Register Patient. The system creates a patient number (P- followed by six digits) and opens the new patient record. To admit them next, choose Admit from their record or use Admissions > New Admission. Doctor and Nurse cannot register patients.',
                patient: 'Open Patients in the sidebar to search by name, patient number, or phone and open a record. Administrator, Reception, and Manager can register or edit patient demographics. Doctors can view demographics but cannot edit them. Nurses can access patients assigned to them. Register each person once; use the existing patient record when creating another admission after discharge.',
                admission: 'Who can: Administrator, Manager, Reception, and Doctor. Steps: (1) Open Admissions > New Admission, or use Admit on a patient record. (2) Search for and select the existing patient. (3) Select an active department and admission type: Emergency, Planned, Transfer In, or Observation. (4) Optionally select an attending doctor and enter the reason/chief complaint. (5) Select Save/Create Admission. The new admission is ACTIVE and opens its detail page. The same patient cannot have two active admissions. Creating an admission does not allocate a bed; an Administrator or Manager does that separately.',
                allocation: 'Who can allocate: Administrator or Manager. Steps: (1) Ensure the patient has an ACTIVE admission. If not, register/find the patient and create an admission first. (2) Ensure an active ward has an active room and an active bed in AVAILABLE status. (3) Open the admission detail page and choose Allocate Bed, or open Admissions > Allocate Bed. (4) Select the active admission that has no active bed. (5) Optionally filter available beds by ward or bed type, select a displayed available bed, then choose Confirm Allocation. The system marks the bed OCCUPIED and records the active allocation. It will reject inactive or non-available beds. If no bed is listed, ask an Administrator/Manager to add or activate the required ward, room, or bed; only an Administrator manages departments. Other roles can view allocation information but cannot allocate.',
                bed: 'The Beds section shows the bed map/list and department, ward, room, and bed records. Administrator and Manager manage wards, rooms, and beds; only Administrator manages departments. To allocate: create an active admission first, then an Administrator or Manager selects that admission and an AVAILABLE bed in Admissions > Allocate Bed. Other roles have view-only allocation access, with nurse patient information limited to assigned patients. A bed must belong to an active room and ward to be available for allocation.',
                transfer: 'Who can: Administrator and Manager can transfer any active, allocated admission; Nurse can transfer only patients assigned to them. Doctors and Reception can view transfer history. Steps: (1) Confirm the patient has an ACTIVE admission and a current bed allocation. (2) Open Admissions > Transfers, or choose Transfer on the admission detail page. (3) Select the patient/admission with a current bed. (4) Select a different destination bed shown as AVAILABLE. (5) Enter the required transfer reason and choose Execute Transfer. The destination becomes OCCUPIED, the old allocation closes, and the old bed becomes CLEANING. Transfers do not discharge or close the admission. If there is no destination bed, an Administrator/Manager must make an active bed available.',
                discharge: 'Who can: Administrator, Manager, Reception, and Doctor, subject to hospital policy. Steps: (1) Open Discharges > Discharge Patient, or choose Discharge on the active admission detail page. (2) Select the ACTIVE admission; the list shows the patient and current bed, if any. (3) Select a discharge type (Recovered, Referred, Left Against Advice, Transferred Out, Deceased, or Other). (4) Confirm or set the discharge date/time and enter an optional summary. (5) Choose Confirm Discharge and confirm the prompt. This closes the admission, records the discharge, closes its active bed allocation, and moves that bed to CLEANING—not directly to AVAILABLE. A patient without a bed can still be discharged. The patient record remains and can be used for future admissions.',
                bed_release: 'After discharge or transfer, the old bed is released from its patient allocation and automatically changes from OCCUPIED to CLEANING. It cannot be allocated while cleaning. A user with bed-status permission (Administrator, Manager, or Nurse) opens Beds, chooses the bed status action, sets the status to AVAILABLE, enters the required reason, and saves after cleaning is complete. From CLEANING, the permitted next statuses are AVAILABLE or MAINTENANCE. Never mark it AVAILABLE before it is ready for use. For maintenance/out-of-service workflows, follow hospital policy.',
                profile: 'Open your user menu in the top-right corner and choose My Profile. Upload a JPEG, PNG, GIF, or WebP image up to 3 MB. You can replace or remove your own profile photo there.',
                password: 'Open your user menu in the top-right corner, choose My Profile, then Change Password. Enter your current password and choose a new password of at least 8 characters that includes a letter and a number or symbol.',
                reports: 'Choose Reports in the sidebar to view or print available reports. What you can see depends on your role; nurse reports are limited to assigned patients.',
                nurse: 'Nurses can view records for patients assigned to them. An Administrator or Manager assigns nurses from an active admission’s details page. If you cannot see a patient, ask an Administrator or Manager to check the assignment.',
                login: 'Sign in with your username and password. After 5 unsuccessful password attempts, the account is locked and an Administrator must help unlock it. Change a temporary or forgotten password only through the authorized account process.',
                beds_empty: 'If no beds or rooms appear for allocation, first check that the admission is ACTIVE and that a bed does not already have an active allocation. An Administrator or Manager can add/activate wards, rooms, and beds; only an Administrator can add/manage departments. Allocation only lists active beds in active rooms and wards with AVAILABLE status. Beds in OCCUPIED, CLEANING, MAINTENANCE, RESERVED, BLOCKED, or OUT_OF_SERVICE status cannot be selected as ordinary available beds.',
                fallback: 'I could not match that wording to a help topic. Try simpler words such as “register patient”, “admission”, “allocate bed”, “transfer bed”, “discharge”, “release bed”, or “full patient workflow”. You can also choose a suggested topic or select Amharic/Afaan Oromoo above. This offline FAQ cannot answer unrestricted questions, but it covers the system workflows and role permissions.',
                privacy: 'This is a built-in FAQ helper, not a generative AI service. Do not enter patient-identifying, medical, password, or other confidential information. It does not read or search patient records.'
            }
        },
        am: {
            title: 'የስርዓት እገዛ',
            subtitle: 'በመረጡት ቋንቋ የሚሰራ የእገዛ መረጃ',
            toggle: 'እገዛ',
            close: 'ውይይቱን ዝጋ',
            languageLabel: 'የውይይት ቋንቋ',
            inputLabel: 'ስለ ስርዓቱ ይጠይቁ',
            placeholder: 'ጥያቄዎን ይጻፉ…',
            send: 'ጥያቄ ላክ',
            greeting: 'ሰላም! የሆስፒታሉን ስርዓት ለመጠቀም እረዳዎታለሁ። ከታች ርዕስ ይምረጡ ወይም በአማርኛ ይጠይቁ።',
            suggestions: [
                ['workflow', 'የታካሚ ሙሉ ሂደት'],
                ['patient_register', 'ታካሚ መመዝገብ'],
                ['admission', 'ምዝገባ መፍጠር'],
                ['allocation', 'አልጋ መመደብ'],
                ['transfer', 'ታካሚ ማዛወር'],
                ['discharge', 'ታካሚ ማስወጣት'],
                ['bed_release', 'አልጋ መልቀቅ']
            ],
            answers: {
                workflow: 'የታካሚ መደበኛ ሂደት፦ (1) Patients > Register Patient ውስጥ ታካሚውን አንድ ጊዜ ይመዝግቡ፤ ስርዓቱ የታካሚ ቁጥር ይሰጣል። (2) Admissions > New Admission ውስጥ ታካሚውን፣ Department እና Admission Type ይምረጡ፤ ዶክተርና ምክንያት አማራጭ ናቸው። ያስቀምጡ፤ Admission ACTIVE ይሆናል። (3) Administrator ወይም Manager Allocate Bed ውስጥ ንቁ አልጋ የሌለውን admission መርጦ AVAILABLE አልጋ ይመድባል፤ አልጋው OCCUPIED ይሆናል። (4) ዝውውር ሲያስፈልግ Administrator/Manager ወይም የተመደበው Nurse በTransfers የተለየ AVAILABLE አልጋ መርጦ ምክንያት ያስገባል። አዲሱ OCCUPIED፣ አሮጌው CLEANING ይሆናል፤ Admission አይዘጋም። (5) Administrator፣ Manager፣ Reception ወይም Doctor በሆስፒታሉ ፖሊሲ መሠረት ታካሚውን ያስወጣል። Admission ይዘጋል፣ የአልጋ ምደባ ይዘጋል፣ አልጋው CLEANING ይሆናል። (6) ጽዳት ሲጠናቀቅ Administrator፣ Manager ወይም Nurse ምክንያት በማስገባት አልጋውን CLEANING ከAVAILABLE ያደርገዋል። ከዚያ በኋላ ብቻ እንደገና ይመደባል። ታካሚው በኋላ እንደገና ቢገባ ነባሩን የታካሚ መዝገብ ይጠቀሙ፤ አዲስ ታካሚ አይፍጠሩ።',
                permissions: 'Administrator ሁሉንም ፈቃዶች አለው። Manager የዋርድና ክፍል አስተዳደርን ጨምሮ የሥራ አስተዳደር ይችላል፤ ዲፓርትመንትን ማየት ብቻ ይችላል፣ የተጠቃሚና ሚና አስተዳደር ግን የተከለከለ ነው። Doctor የታካሚና የምዝገባ መረጃ ማየትና የሕክምና ዝርዝር ማዘመን ይችላል። Nurse ለእሱ/ሷ የተመደቡ ታካሚዎችንና የተፈቀዱ የአልጋ ሥራዎችን ይደርሳል። Reception ታካሚ መመዝገብና ማስተካከል ይችላል።',
                patient_register: 'ማን ይችላል፦ Administrator፣ Reception እና Manager። ደረጃዎች፦ (1) Patients > Register Patient ይክፈቱ። (2) ሙሉ ስም፣ ጾታ እና የትውልድ ቀን ያስገቡ፤ እነዚህ አስፈላጊ ናቸው። ስልክ፣ አድራሻና የአደጋ ጊዜ ተጠሪ አማራጭ ናቸው። (3) Register Patient ይጫኑ። ስርዓቱ P- በሚል የሚጀምር ልዩ ቁጥር ይሰጣል እና መዝገቡን ይከፍታል። ቀጥሎ Admit ይጫኑ ወይም Admissions > New Admission ይክፈቱ። Doctor እና Nurse ታካሚ መመዝገብ አይችሉም።',
                patient: 'ከጎን ምናሌው Patients ይክፈቱ፤ በስም፣ በታካሚ ቁጥር ወይም በስልክ ይፈልጉ። Administrator፣ Reception እና Manager የታካሚ መረጃ መመዝገብና ማስተካከል ይችላሉ። Doctor ማየት ብቻ ይችላል፤ Nurse ለእሱ/ሷ የተመደቡ ታካሚዎችን ብቻ ያያል። ከወጣ በኋላ ታካሚውን እንደገና አይመዝግቡ፤ ነባሩን መዝገብ ለአዲስ admission ይጠቀሙ።',
                admission: 'ማን ይችላል፦ Administrator፣ Manager፣ Reception እና Doctor። (1) Admissions > New Admission ይክፈቱ ወይም በታካሚው መዝገብ Admit ይጫኑ። (2) ነባሩን ታካሚ ይፈልጉና ይምረጡ። (3) ንቁ Department እና የAdmission Type (Emergency፣ Planned፣ Transfer In ወይም Observation) ይምረጡ። (4) Attending Doctor እና ምክንያት ካሉ ያስገቡ። (5) ያስቀምጡ። Admission ACTIVE ይሆናል እና ዝርዝሩ ይከፈታል። አንድ ታካሚ አንድ ንቁ admission ብቻ ሊኖረው ይችላል። Admission መፍጠር አልጋ አይመድብም፤ Administrator/Manager በተለየ ይመድባል።',
                allocation: 'ማን ይመድባል፦ Administrator ወይም Manager። (1) ACTIVE admission ያረጋግጡ። (2) ንቁ Ward፣ Room እና AVAILABLE የሆነ ንቁ Bed መኖሩን ያረጋግጡ። (3) ከAdmission ዝርዝር Allocate Bed ይጫኑ ወይም Admissions > Allocate Bed ይክፈቱ። (4) ንቁ አልጋ የሌለውን admission ይምረጡ። (5) የሚታየውን AVAILABLE አልጋ ይምረጡና Confirm Allocation ይጫኑ። ስርዓቱ አልጋውን OCCUPIED ያደርጋል። አልጋ ካልታየ ንቁ ዋርድ/ክፍል/አልጋ መኖሩን ይጠይቁ። ክፍልና ዋርድ Administrator/Manager ይጨምራሉ፤ Department ግን Administrator ብቻ።',
                bed: 'Beds ክፍል የአልጋ፣ ክፍል፣ ዋርድና Department መረጃን ያሳያል። Administrator እና Manager ዋርድ፣ ክፍልና አልጋ ያስተዳድራሉ፤ Department የሚያስተዳድረው Administrator ብቻ ነው። አልጋ ለመመደብ ንቁ admission ያስፈልጋል፤ Administrator/Manager ንቁ አልጋ የሌለውን admission እና AVAILABLE አልጋ መርጠው Confirm Allocation ይጫኑ።',
                transfer: 'ማን ይችላል፦ Administrator/Manager ማንኛውንም ንቁ እና አልጋ የተመደበለት admission ያዛውራሉ፤ Nurse ለእሱ/ሷ የተመደበውን ታካሚ ብቻ። (1) Admissions > Transfers ይክፈቱ ወይም በAdmission ዝርዝር Transfer ይጫኑ። (2) አሁን አልጋ ያለውን ታካሚ ይምረጡ። (3) የተለየ AVAILABLE መድረሻ አልጋ ይምረጡ። (4) የዝውውር ምክንያት አስገብተው Execute Transfer ይጫኑ። አዲሱ አልጋ OCCUPIED ይሆናል፤ አሮጌው ምደባ ይዘጋና አሮጌው አልጋ CLEANING ይሆናል። Admission አይዘጋም።',
                discharge: 'ማን ይችላል፦ Administrator፣ Manager፣ Reception እና Doctor፤ የሆስፒታሉን ፖሊሲ ይከተሉ። (1) Discharges > Discharge Patient ይክፈቱ ወይም በንቁ Admission ዝርዝር Discharge ይጫኑ። (2) ACTIVE admission ይምረጡ። (3) የመውጫ አይነት ይምረጡ። (4) ቀን/ሰዓት ያረጋግጡና አማራጭ ማጠቃለያ ያስገቡ። (5) Confirm Discharge ይጫኑና ያረጋግጡ። Admission ይዘጋል፤ የመውጫ መዝገብ ይፈጠራል፤ የአልጋ ምደባ ይዘጋና አልጋው CLEANING ይሆናል፣ በቀጥታ AVAILABLE አይሆንም። አልጋ ያልተመደበለት ታካሚም መውጣት ይችላል። የታካሚ መዝገቡ ይቀራል።',
                bed_release: 'ከታካሚ መውጣት ወይም ዝውውር በኋላ አሮጌው የአልጋ ምደባ ይዘጋና አልጋው በራስ-ሰር OCCUPIED ከሆነበት CLEANING ይሆናል። በCLEANING ላይ አልጋ መመደብ አይቻልም። Administrator፣ Manager ወይም Nurse ከBeds ውስጥ የአልጋ ሁኔታን ቀይሮ CLEANING ከAVAILABLE ይመርጣል፣ ምክንያት ያስገባልና ያስቀምጣል። ጽዳት እስኪጠናቀቅ አልጋውን AVAILABLE አያድርጉ።',
                profile: 'ከላይ በቀኝ ያለውን የተጠቃሚ ምናሌ ከፍተው My Profile ይምረጡ። JPEG፣ PNG፣ GIF ወይም WebP ፎቶ እስከ 3 MB መጫን፣ መቀየር ወይም ማስወገድ ይችላሉ።',
                password: 'ከላይ በቀኝ ያለውን የተጠቃሚ ምናሌ ከፍተው My Profile፣ ከዚያ Change Password ይምረጡ። የአሁኑን የይለፍ ቃል ያስገቡና ቢያንስ 8 ቁምፊ ያለው ፊደል እና ቁጥር ወይም ምልክት የያዘ አዲስ ይምረጡ።',
                reports: 'በጎን ምናሌ Reports በመክፈት ሪፖርቶችን ማየትና ማተም ይችላሉ። የሚታየው በሚናዎ ይወሰናል፤ የNurse ሪፖርቶች በተመደቡለት ታካሚዎች ብቻ ይገደባሉ።',
                nurse: 'Nurse ለእሱ/ሷ የተመደቡ ታካሚዎችን ይመለከታል። Administrator ወይም Manager ከንቁ የታካሚ ምዝገባ ዝርዝር ውስጥ ነርስ ይመድባል። ታካሚ ካልታየ ምደባውን እንዲያረጋግጡ ይጠይቁ።',
                login: 'በusername እና የይለፍ ቃል ይግቡ። የይለፍ ቃል 5 ጊዜ በስህተት ከገባ መለያው ይቆለፋል፤ Administrator ያግዝዎታል።',
                beds_empty: 'አልጋ ለመመደብ ካልታየ፣ መጀመሪያ admission ACTIVE መሆኑንና አስቀድሞ ንቁ አልጋ እንዳልተመደበለት ያረጋግጡ። Administrator/Manager ንቁ ዋርድ፣ ክፍልና አልጋ ይጨምራሉ ወይም ያንቀሳቅሳሉ፤ Department የሚያስተዳድረው Administrator ብቻ ነው። ለምደባ የሚታዩት ንቁ ዋርድና ክፍል ውስጥ AVAILABLE የሆኑ አልጋዎች ብቻ ናቸው።',
                fallback: 'የጻፉትን ጥያቄ ከእገዛ ርዕሶች ጋር ማዛመድ አልቻልኩም። በቀላል ቃላት “ታካሚ መመዝገብ”፣ “admission”፣ “አልጋ መመደብ”፣ “ዝውውር”፣ “ታካሚ ማስወጣት”፣ “አልጋ መልቀቅ” ወይም “ሙሉ ሂደት” ብለው ይጠይቁ። ከታች ያለ የርዕስ አዝራር ይምረጡ።',
                privacy: 'ይህ በስርዓቱ ውስጥ የተዘጋጀ የጥያቄና መልስ እገዛ ነው፤ የAI አገልግሎት አይደለም። የታካሚ ማንነት፣ የሕክምና መረጃ፣ የይለፍ ቃል ወይም ሌላ ሚስጥራዊ መረጃ አያስገቡ። መዝገቦችን አያነብም።'
            }
        },
        om: {
            title: 'Gargaarsa Sirnaa',
            subtitle: 'Gargaarsa afaan filataniin',
            toggle: 'Gargaarsa',
            close: 'Mariisaa cufi',
            languageLabel: 'Afaan mariisaa',
            inputLabel: 'Waa’ee sirnichaa gaafadhu',
            placeholder: 'Gaaffii kee barreessi…',
            send: 'Ergaa ergi',
            greeting: 'Akkam! Sirna hospitaalaa kana akkamitti akka fayyadamtan nan gargaara. Mata-duree armaan gadii filadhu ykn Afaan Oromootiin na gaafadhu.',
            suggestions: [
                ['workflow', 'Adeemsa dhukkubsataa guutuu'],
                ['patient_register', 'Dhukkubsataa galmeessi'],
                ['admission', 'Admission uumi'],
                ['allocation', 'Siree ramadi'],
                ['transfer', 'Dhukkubsataa jijjiiri'],
                ['discharge', 'Dhukkubsataa baasi'],
                ['bed_release', 'Siree gadhiisi']
            ],
            answers: {
                workflow: 'Adeemsa dhukkubsataa idilee: (1) Patients > Register Patient keessatti yeroo tokko galmeessi; sirni lakkoofsa dhukkubsataa kenna. (2) Admissions > New Admission keessatti dhukkubsataa, department fi gosa admission filadhu; doctor fi sababaan filannoo dha. Save godhi; admission ACTIVE ta’a. (3) Administrator yookaan Manager Allocate Bed keessatti admission ACTIVE siree hin qabne filatee siree AVAILABLE ramada; sireen OCCUPIED ta’a. (4) Siree jijjiiruun yoo barbaachise Administrator/Manager yookaan Nurse dhukkubsataa sanaaf ramadame Transfers keessatti siree AVAILABLE biraa filatee sababaa galcha. Sireen haaraan OCCUPIED, inni duraanii CLEANING ta’a; admission hin cufamu. (5) Administrator, Manager, Reception yookaan Doctor imaammata hospitaalaa hordofee admission ACTIVE discharge godha. Admission fi ramaddiin siree ni cufamu; sireen CLEANING ta’a. (6) Erga qulqullaa’ee booda Administrator, Manager yookaan Nurse sababaa galchee siree CLEANING irraa AVAILABLE godha. Sana booda qofa irra deebi’amee ramadama. Dhukkubsataan gara fuulduraatti deebi’ee yoo dhufe galmee duraanii fayyadami; galmee dhukkubsataa lammaffaa hin uumin.',
                permissions: 'Administrator eeyyama guutuu qaba. Manager hojii bulchiinsaa, ward fi room bulchuu dabalatee ni danda’a; department ilaaluu qofa danda’a, bulchiinsi user fi role immoo daangeffameera. Doctor galmee dhukkubsataa ilaaluu fi odeeffannoo yaalaa fooyyessuu danda’a. Nurse dhukkubsattoota isaaf ramadaman fi hojii siree/transfer hayyamame qofa argata. Reception dhukkubsataa galmeessuu fi odeeffannoo isaa fooyyessuu danda’a.',
                patient_register: 'Eenyyu galmeessuu danda’a: Administrator, Reception fi Manager. (1) Patients > Register Patient bani. (2) Maqaa guutuu, saala fi guyyaa dhalootaa galchi; dirqama. Lakkoofsa bilbilaa, teessoo fi nama yeroo hatattamaa filannoo dha. (3) Register Patient cuqaasi. Sirni lakkoofsa adda ta’e P- jalqabu kenna, achiis galmee dhukkubsataa bana. Itti aansuun Admit cuqaasi yookaan Admissions > New Admission bani. Doctor fi Nurse dhukkubsataa galmeessuu hin danda’an.',
                patient: 'Menu Patients keessaa dhukkubsataa maqaa, lakkoofsa dhukkubsataa yookaan bilbilaatiin barbaadi. Administrator, Reception fi Manager galmeessuu fi odeeffannoo gulaaluu danda’u. Doctor ilaala malee odeeffannoo dhuunfaa hin gulaalu. Nurse dhukkubsattoota isaaf ramadaman qofa ilaala. Dhukkubsataa tokko yeroo ba’u irra deebi’ii hin galmeessin; admission haaraaf galmee duraanii fayyadami.',
                admission: 'Eenyyu uumu danda’a: Administrator, Manager, Reception fi Doctor. (1) Admissions > New Admission bani yookaan galmee dhukkubsataa keessaa Admit cuqaasi. (2) Dhukkubsataa duraan galmaa’e barbaadiitii filadhu. (3) Department hojii irra jiru fi gosa admission (Emergency, Planned, Transfer In yookaan Observation) filadhu. (4) Doctor itti gaafatamaa fi sababaa yoo barbaachise galchi. (5) Save uumi. Admission haaraan ACTIVE ta’ee fuulli isaa bana. Dhukkubsataan tokko admission ACTIVE tokko qofa qabaachuu danda’a. Admission uumuun siree hin ramadu; Administrator yookaan Manager addaan ramada.',
                allocation: 'Eenyyu siree ramadu danda’a: Administrator yookaan Manager. (1) Admission dhukkubsataa ACTIVE ta’uu mirkaneessi. (2) Ward hojii irra jiru, room hojii irra jiru fi sireen AVAILABLE ta’e jiraachuu mirkaneessi. (3) Fuula admission keessaa Allocate Bed cuqaasi yookaan Admissions > Allocate Bed bani. (4) Admission siree ACTIVE hin qabne filadhu. (5) Siree AVAILABLE mul’atu filadhuutii Confirm Allocation cuqaasi. Sirni siree OCCUPIED godha. Sireen yoo hin mul’anne Administrator/Manager akka ward, room yookaan bed hojii irra jiru dabalu yookaan sirreessu gaafadhu. Department Administrator qofa bulcha.',
                bed: 'Kutaan Beds siree, room, ward fi department agarsiisa. Administrator fi Manager ward, room fi bed bulchu; Department Administrator qofa bulcha. Siree ramaduuf admission ACTIVE qabaachuu qabda. Administrator/Manager admission siree ACTIVE hin qabne fi siree AVAILABLE filatee Confirm Allocation cuqaasa. Sireen hojii irra jiru room fi ward hojii irra jiru keessa jiraachuu qaba.',
                transfer: 'Eenyyu jijjiiruu danda’a: Administrator/Manager admission ACTIVE siree qabuu kamiyyuu jijjiira; Nurse dhukkubsataa isaaf ramadame qofa. (1) Admissions > Transfers bani yookaan admission keessaa Transfer cuqaasi. (2) Dhukkubsataa siree ammaa qabu filadhu. (3) Siree biraa AVAILABLE ta’e filadhu. (4) Sababa jijjiirraa galchiitii Execute Transfer cuqaasi. Ramaddiin siree haaraa ni uumama, sireen haaraan OCCUPIED ta’a; ramaddiin durii ni cufama, sireen durii CLEANING ta’a. Admission ACTIVE ta’ee hafa.',
                discharge: 'Eenyyu discharge gochuu danda’a: Administrator, Manager, Reception fi Doctor hayyama qaban; imaammata hospitaalaa hordofi. (1) Discharges > Discharge Patient bani yookaan fuula admission ACTIVE keessaa Discharge cuqaasi. (2) Admission ACTIVE filadhu. (3) Gosa discharge filadhu. (4) Guyyaa/sa’aatii mirkaneessi; summary filannoo dha. (5) Confirm Discharge cuqaasiitii mirkaneessi. Admission ni cufama, galmeen discharge ni uumama, ramaddiin siree ni cufama, sireen CLEANING ta’a malee battalumatti AVAILABLE hin ta’u. Dhukkubsataan siree hin qabnes discharge gochuun ni danda’ama. Galmeen dhukkubsataa ni hafa; gara fuulduraatti fayyadami.',
                bed_release: 'Discharge yookaan transfer booda ramaddiin siree durii ni cufama; sireen OCCUPIED irraa ofumaan gara CLEANING jijjiirama. Yeroo CLEANING siree ramaduun hin danda’amu. Administrator, Manager yookaan Nurse Beds keessaa status siree jijjiiruun CLEANING irraa AVAILABLE filata, sababaa galchee save godha; erga qulqullaa’ee booda qofa AVAILABLE godhi. CLEANING irraa AVAILABLE yookaan MAINTENANCE filachuun ni danda’ama. Qulqullinni osoo hin xumuramin siree AVAILABLE hin godhin.',
                profile: 'Menu user gubbaa mirgaa jiru baniitii My Profile filadhu. Suuraa JPEG, PNG, GIF yookaan WebP hanga 3 MB olkaa’uu, jijjiiruu yookaan haqachuu dandeessa.',
                password: 'Menu user gubbaa mirgaa bani, My Profile fi achii Change Password filadhu. Password ammaa galchi; password haaraan yoo xiqqaate qubee 8 qabaachuu, qubee fi lakkoofsa yookaan mallattoo of keessaa qabaachuu qaba.',
                reports: 'Menu Reports banii gabaasa ilaali yookaan maxxansi. Odeeffannoon argamu role kee irratti hundaa’a; gabaasni Nurse dhukkubsattoota isaaf ramadaman qofa irratti daangeffama.',
                nurse: 'Nurse dhukkubsattoota isaaf ramadaman ilaala. Administrator yookaan Manager fuula odeeffannoo admission hojii irra jiru irraa Nurse ramada. Dhukkubsataan yoo hin mul’anne ramaddii akka mirkaneessan gaafadhu.',
                login: 'Username fi password keetiin seeni. Password dogoggoraan yeroo 5 galchuun account cufa; Administrator qulfii banuuf si gargaara.',
                beds_empty: 'Sireen ramaddii keessatti yoo hin mul’anne, admission ACTIVE ta’uu fi sireen ACTIVE duraan itti hin ramadamne mirkaneessi. Administrator/Manager ward, room fi bed hojii irra jiru dabalu yookaan hojii irra oolchuu danda’u; Department Administrator qofa bulcha. Ramaddii keessatti siree AVAILABLE qofa argita. Sireen OCCUPIED, CLEANING, MAINTENANCE, RESERVED, BLOCKED yookaan OUT_OF_SERVICE ta’e hin filatamu.',
                fallback: 'Jechi gaaffii kee mata-dureewwan gargaarsaa waliin wal-simsiisuu hin dandeenye. Jechoota salphaa “dhukkubsataa galmeessi”, “admission”, “siree ramadi”, “siree jijjiiri”, “dhukkubsataa baasi”, “siree gadhiisi” yookaan “adeemsa guutuu” fayyadami. Yookaan mata-duree gadii keessaa filadhu.',
                privacy: 'Kun gargaarsa FAQ sirna keessatti kuufame dha; tajaajila AI miti. Maqaa dhukkubsataa, odeeffannoo yaalaa, password yookaan odeeffannoo iccitii hin galchin. Galmee dhukkubsataa hin dubbisu.'
            }
        }
    };

    const intents = [
        { id: 'workflow', terms: ['full workflow', 'whole workflow', 'patient journey', 'how this system works', 'how this system work', 'how does this system work', 'how does the system work', 'how system work', 'from registration to discharge', 'from register to discharge', 'patient registration and bed allocation', 'admission bed allocation transfer discharge', 'registration admission allocation transfer discharge', 'complete process', 'workflow', 'adeemsa guutuu', 'adeemsa dhukkubsataa', 'sirni akkamitti', 'dhukkubsataa galmeessuu irraa', 'galmee siree jijjiirraa baasi', 'አጠቃላይ ሂደት', 'ሙሉ ሂደት', 'ስርዓቱ እንዴት', 'ከምዝገባ እስከ መውጫ', 'ታካሚ መመዝገብ አልጋ መመደብ'] },
        { id: 'permissions', terms: ['permission', 'role', 'access', 'who can', 'eeyyama', 'gahee', 'eenyu danda', 'ፈቃድ', 'ሚና', 'ማን ይችላል'] },
        { id: 'patient_register', terms: ['register patient', 'new patient', 'create patient', 'patient registration', 'register a patient', 'register someone', 'galmeessi', 'galmeess', 'galmaa', 'dhukkubsataa galmeessi', 'galmeessa dhukkubsataa', 'akkamitti galmeessa', 'የታካሚ ምዝገባ', 'ታካሚ መመዝገብ', 'አዲስ ታካሚ', 'ታካሚ መመዝገቢያ', 'አዲስ ታካሚ ማስገባት'] },
        { id: 'allocation', terms: ['allocate bed', 'bed allocation', 'assign bed', 'assign a bed', 'give a bed', 'get a bed', 'bed assignment', 'put patient in bed', 'put the patient in a bed', 'place patient in bed', 'give patient a bed', 'bed for patient', 'bed for admission', 'ramadi', 'ramadu', 'siree ramadi', 'siree kenni', 'dhukkubsataa siree irra', 'dhukkubsataaf siree', 'ታካሚ አልጋ', 'ታካሚን አልጋ', 'አልጋ ላይ', 'አልጋ መመደብ', 'አልጋ መድብ', 'አልጋ ስጥ'] },
        { id: 'admission', terms: ['admission', 'admit', 'admissions', 'new admission', 'create admission', 'seensaa', 'galmee admission', 'galmee dhukkubsataa', 'ምዝገባ', 'ማስገባት', 'አዲስ admission'] },
        { id: 'transfer', terms: ['transfer', 'move bed', 'move patient', 'move to another bed', 'another bed', 'different bed', 'bed transfer', 'transfer bed', 'change bed', 'change ward', 'patient transfer', 'jijjiir', 'siree jijjiiri', 'siree biraa', 'dhukkubsataa siree biraatti', 'ward biraatti', 'ዝውውር', 'ማዛወር', 'አልጋ ቀይር', 'ወደ ሌላ አልጋ', 'ታካሚ አዛውር'] },
        { id: 'discharge', terms: ['discharge', 'leave hospital', 'discharge patient', 'patient discharge', 'send patient home', 'release patient', 'baas', 'ba’uu', 'dhukkubsataa baasi', 'mana yaalaa baasi', 'የታካሚ መውጫ', 'ማስወጣት', 'ታካሚ አስወጣ', 'ታካሚን ወደ ቤት'] },
        { id: 'bed_release', terms: ['release bed', 'bed release', 'free bed', 'make bed available', 'bed cleaning', 'after discharge', 'after transfer', 'gadhiisi', 'siree gadhiisi', 'siree qulqulleessi', 'አልጋ ልቀቅ', 'አልጋ አጽዳ', 'አልጋ available', 'ከመውጣት በኋላ'] },
        { id: 'patient', terms: ['patient', 'search patient', 'edit patient', 'find patient', 'dhukkubsat', 'የታካሚ', 'ታካሚ', 'ፈልግ'] },
        { id: 'profile', terms: ['profile photo', 'profile picture', 'photo', 'picture', 'suuraa', 'piroofaayilii', 'ፎቶ', 'ምስል'] },
        { id: 'password', terms: ['password', 'change password', 'forgot password', 'jecha icciitii', 'ፓስዎርድ', 'የይለፍ ቃል'] },
        { id: 'nurse', terms: ['nurse', 'assigned', 'assignment', 'ramad', 'ነርስ', 'ተመድቦ', 'ምደባ'] },
        { id: 'reports', terms: ['report', 'print', 'gabaasa', 'maxxans', 'ሪፖርት', 'ማተም'] },
        { id: 'beds_empty', terms: ['no bed', 'no beds', 'empty bed', 'room', 'bed', 'ward', 'department', 'available bed', 'siree', 'kutaa', 'wardii', 'ramaddii', 'የለም', 'አልጋ', 'ክፍል', 'ዋርድ', 'ዲፓርትመንት'] },
        { id: 'login', terms: ['login', 'sign in', 'locked', 'password error', 'seeni', 'መግባት', 'ተቆልፏል'] },
        { id: 'privacy', terms: ['ai', 'chatbot', 'chat bot', 'privacy', 'private', 'confidential', 'iccitii', 'ሚስጥር', 'የግል መረጃ'] }
    ];

    const normalize = function (value) {
        return value.toLocaleLowerCase().normalize('NFC').replace(/[’']/g, '\'').trim();
    };

    const addMessage = function (value, isUser) {
        const item = document.createElement('div');
        item.className = 'system-chat-message' + (isUser ? ' is-user' : '');
        item.textContent = value;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    };

    const getAnswer = function (query, language) {
        const normalized = normalize(query);
        let selectedId = 'fallback';
        let highestScore = 0;
        intents.forEach(function (intent) {
            const score = intent.terms.reduce(function (total, term) {
                return total + (normalized.includes(normalize(term)) ? 1 : 0);
            }, 0);
            if (score > highestScore) {
                selectedId = intent.id;
                highestScore = score;
            }
        });
        return text[language].answers[selectedId];
    };

    const setLanguage = function (language) {
        const content = text[language];
        root.querySelector('[data-chat-title]').textContent = content.title;
        root.querySelector('[data-chat-subtitle]').textContent = content.subtitle;
        root.querySelector('[data-chat-toggle-label]').textContent = content.toggle;
        close.setAttribute('aria-label', content.close);
        root.querySelector('[data-chat-language-label]').textContent = content.languageLabel;
        root.querySelector('[data-chat-input-label]').textContent = content.inputLabel;
        root.querySelector('[data-chat-send]').setAttribute('aria-label', content.send);
        input.placeholder = content.placeholder;
        suggestions.replaceChildren();
        content.suggestions.forEach(function (suggestion) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'system-chat-suggestion';
            button.textContent = suggestion[1];
            button.dataset.chatIntent = suggestion[0];
            suggestions.appendChild(button);
        });
        if (messages.childElementCount === 0) addMessage(content.greeting, false);
    };

    const openChat = function (open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) input.focus();
    };

    toggle.addEventListener('click', function () {
        openChat(panel.hidden);
    });
    close.addEventListener('click', function () {
        openChat(false);
        toggle.focus();
    });
    root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            openChat(false);
            toggle.focus();
        }
    });
    languageSelect.addEventListener('change', function () {
        setLanguage(languageSelect.value);
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const query = input.value.trim();
        if (!query) return;
        const language = languageSelect.value;
        addMessage(query, true);
        addMessage(getAnswer(query, language), false);
        input.value = '';
        input.focus();
    });
    suggestions.addEventListener('click', function (event) {
        const button = event.target.closest('[data-chat-intent]');
        if (!button) return;
        const language = languageSelect.value;
        const selected = text[language].suggestions.find(function (item) {
            return item[0] === button.dataset.chatIntent;
        });
        if (!selected) return;
        addMessage(selected[1], true);
        addMessage(text[language].answers[selected[0]], false);
    });

    setLanguage(languageSelect.value);
});
