<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OffenseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // An existing catalog may contain approved edits and archived entries.
        if (DB::table('offense_catalog')->exists()) {
            return;
        }

        $minor = [
            [
                'code' => 'MINOR-01',
                'title' => 'Non-wearing of prescribed uniform',
                'description' => 'Non-wearing of the prescribed uniform inside the campus.',
                'keywords' => ['uniform', 'no uniform', 'not wearing uniform', 'without uniform', 'civilian clothes', 'out of uniform', 'improper uniform', 'dress code', 'was seen not wearing uniform', 'observed without uniform', 'found not in uniform', 'arrived without uniform', 'entered campus without uniform', 'reported not wearing uniform', 'came to school in civilian', 'not in prescribed uniform', 'caught without uniform', 'spotted without uniform'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-02',
                'title' => 'Non-wearing or failure to bring University ID',
                'description' => 'Non-wearing or failure to bring the University ID on campus or during official University activities outside the campus.',
                'keywords' => ['id', 'no id', 'without id', 'forgot id', 'no student id', 'missing id', 'university id', 'school id', 'no identification', 'was seen without id', 'observed without id', 'found without id', 'entered without id', 'arrived without id', 'failed to present id', 'could not produce id', 'left id at home', 'no id presented', 'reported without id', 'caught without id'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-03',
                'title' => 'Wearing inappropriate attire',
                'description' => 'Wearing inappropriate attire within the University premises, such as but not limited to: torn, worn-out, or ripped jeans or bottoms; bare-shouldered top (sleeveless, spaghetti straps, razor back, tube, strapless, halter); top with plunging neckline, exposed back, or midriff; shorts and skirts more than two inches above the knee; figure-hugging attire; see-through or sheer attire; joggers, jogging pants, sweatpants, leggings, colored or lace stockings; slippers, flip-flops, slip-ons, slides; cross-dressing.',
                'keywords' => ['inappropriate attire', 'ripped jeans', 'torn jeans', 'sleeveless', 'spaghetti straps', 'tube top', 'crop top', 'shorts', 'skirt', 'slippers', 'flip flops', 'leggings', 'jogging pants', 'sweatpants', 'see-through', 'halter', 'strapless', 'cross-dressing', 'figure-hugging', 'was seen wearing slippers', 'observed wearing shorts', 'found wearing inappropriate', 'caught wearing sleeveless', 'reported wearing ripped jeans', 'arrived wearing slippers', 'entered campus wearing flip flops', 'seen wearing tube top', 'spotted wearing leggings', 'observed in inappropriate clothing', 'was wearing attire not compliant', 'student came in wearing'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-04',
                'title' => 'Wearing clothing with inappropriate language or suggestive graphics',
                'description' => 'Wearing clothing with inappropriate language and suggestive graphics that do not conform to the University\'s values.',
                'keywords' => ['inappropriate shirt', 'offensive graphics', 'suggestive graphics', 'inappropriate print', 'offensive language on clothing', 'vulgar shirt', 'offensive clothing', 'was wearing shirt with offensive', 'observed wearing clothing with', 'found wearing inappropriate graphics', 'spotted wearing vulgar shirt', 'reported wearing offensive print', 'seen wearing suggestive clothing'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-05',
                'title' => 'Using classroom, facilities, or equipment without reservation or proper authority',
                'description' => 'Using the classroom, facilities, or equipment without reservation or proper authority.',
                'keywords' => ['unauthorized use', 'no reservation', 'facility violation', 'classroom without permission', 'using equipment without authority', 'no booking', 'unauthorized classroom use', 'was found using', 'caught using facility without', 'observed using classroom without', 'found inside without reservation', 'reported using equipment without authority', 'using room without permit', 'using lab without authorization', 'found using computer without'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-06',
                'title' => 'Loitering along classroom corridors while classes are ongoing',
                'description' => 'Loitering along the classroom corridors while classes are going on.',
                'keywords' => ['loitering', 'corridor', 'hallway', 'wandering', 'roaming', 'outside class', 'loitering in corridor', 'during class hours', 'was found loitering', 'observed loitering', 'caught loitering', 'seen wandering in hallway', 'found outside classroom during class', 'spotted in corridor during class', 'reported loitering', 'observed roaming hallways', 'found in corridor while class is going on', 'not inside classroom during class hours'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-07',
                'title' => 'Eating in classrooms, laboratories, offices, libraries, or study areas',
                'description' => 'Eating in classrooms, laboratories, offices, libraries, and study areas.',
                'keywords' => ['eating in class', 'food in classroom', 'eating in laboratory', 'food in lab', 'snacks in classroom', 'drinking in class', 'eating inside', 'food inside', 'was found eating', 'caught eating inside', 'observed eating in classroom', 'seen eating in laboratory', 'reported eating inside', 'found with food inside class', 'spotted eating in library', 'caught with snacks in classroom', 'observed consuming food', 'student was eating during class', 'found drinking inside classroom'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-08',
                'title' => 'Littering',
                'description' => 'Littering inside the University premises.',
                'keywords' => ['littering', 'garbage', 'trash', 'litter', 'waste', 'throwing garbage', 'improper waste disposal', 'throwing trash', 'was seen littering', 'caught throwing garbage', 'observed littering', 'found throwing trash', 'reported littering', 'seen throwing wrapper', 'caught leaving garbage', 'spotted littering on campus', 'student was caught throwing trash'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-09',
                'title' => 'Rearranging furniture without approval',
                'description' => 'Rearranging the tables, chairs, and other fixtures in classrooms, laboratories, or the library without approval.',
                'keywords' => ['rearranging furniture', 'moving chairs', 'rearranging tables', 'furniture without approval', 'moved fixtures', 'rearranged classroom', 'was found rearranging', 'caught moving furniture', 'observed rearranging chairs', 'reported moving tables without approval', 'found moving fixtures', 'seen moving classroom furniture'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-10',
                'title' => 'Violating policies on the use of lockers',
                'description' => 'Violating the policies on the use of lockers.',
                'keywords' => ['locker', 'locker policy', 'locker violation', 'unauthorized locker', 'locker misuse', 'was found violating locker', 'caught misusing locker', 'observed using locker without', 'reported locker violation', 'found using unauthorized locker'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-11',
                'title' => 'Concealing or hiding library materials',
                'description' => 'Concealing or hiding library materials in any area of the library for one\'s exclusive use.',
                'keywords' => ['concealing library', 'hiding library materials', 'hidden book', 'library materials', 'reserve book', 'hiding books', 'concealing books', 'was found hiding library', 'caught concealing books', 'observed hiding library materials', 'found with concealed library book', 'reported hiding materials in library', 'spotted hiding books'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-12',
                'title' => 'Dyeing hair with artificial color',
                'description' => 'Dyeing hair with artificial color is deemed inappropriate by the University.',
                'keywords' => ['hair color', 'dyed hair', 'colored hair', 'hair dye', 'unnatural hair color', 'artificial hair color', 'bleached hair', 'highlights', 'ombre hair', 'was observed with colored hair', 'found with dyed hair', 'reported having colored hair', 'seen with highlighted hair', 'student has bleached hair', 'observed wearing hair color', 'came to school with dyed hair'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-13',
                'title' => 'Wearing prohibited body-piercing accessories',
                'description' => 'Male students are not allowed to wear body-piercing accessories. Female students may wear earrings, limited to one (1) earring per ear lobe.',
                'keywords' => ['piercing', 'body piercing', 'earring', 'multiple earrings', 'nose ring', 'prohibited accessories', 'male earring', 'was seen wearing earrings', 'observed with body piercing', 'found wearing nose ring', 'reported wearing prohibited accessories', 'caught wearing multiple earrings', 'spotted with piercing', 'male student wearing earring'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-14',
                'title' => 'Presence in areas designated for the opposite sex',
                'description' => 'Presence of the opposite sex in areas designated exclusively for the use of either the male or the female sex, e.g., ladies\' and men\'s rooms.',
                'keywords' => ['opposite sex', 'restricted area', 'comfort room', 'bathroom', 'gendered area', 'restroom', 'male female area', 'prohibited area', 'wrong restroom', 'was found inside', 'caught inside ladies room', 'observed entering', 'reported entering wrong restroom', 'found in female restroom', 'spotted inside male restroom', 'entered area designated for opposite sex', 'was seen inside comfort room for', 'found in cr designated for'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MINOR-15',
                'title' => 'Bypassing the student entrance to bring items inside University premises',
                'description' => 'Bypassing the student entrance to bring any item inside the University premises.',
                'keywords' => ['bypass entrance', 'gate bypass', 'unauthorized entry', 'sneaking in', 'bypassing gate', 'bypass student entrance', 'bring in items', 'evading entrance', 'was caught bypassing', 'observed bypassing gate', 'found sneaking in', 'reported bypassing entrance', 'caught evading gate', 'seen bypassing student entrance', 'caught bringing items through', 'spotted bypassing security', 'caught not going through proper entrance'],
                'special_consequence' => null,
            ],
        ];

        $major = [
            [
                'code' => 'MAJOR-01',
                'title' => 'Cheating or academic dishonesty',
                'description' => 'Cheating or academic dishonesty, in online or face-to-face settings, before or during an examination, such as quizzes, long tests, written reports, including papers, case analysis, experiments, or assignments required, and misuse or abuse of Gen AI.',
                'keywords' => ['cheating', 'academic dishonesty', 'plagiarism', 'copying', 'cheat sheet', 'unauthorized notes', 'exam cheating', 'test cheating', 'academic fraud', 'gen ai misuse', 'ai abuse', 'leaking exam', 'proxy exam', 'was caught cheating', 'observed copying during exam', 'found with unauthorized notes', 'caught with cheat sheet', 'seen looking at another student paper', 'reported cheating during', 'caught using phone during exam', 'found using unauthorized materials', 'was caught plagiarizing', 'observed using notes without permission', 'student had notes during', 'caught glancing at exam paper', 'found with kodigo', 'reported for academic dishonesty'],
                'special_consequence' => 'zero_grade_in_output',
            ],
            [
                'code' => 'MAJOR-02',
                'title' => 'Unjust enrichment or stealing',
                'description' => 'Unjust enrichment or stealing, whether attempted, frustrated, or consummated.',
                'keywords' => ['stealing', 'theft', 'unjust enrichment', 'stolen', 'robbery', 'taking property', 'taking items', 'pilferage', 'was caught stealing', 'observed taking', 'found with stolen', 'reported stealing', 'caught in the act of stealing', 'seen taking item without permission', 'caught pilfering', 'found with item that belongs to', 'reported for theft', 'caught stealing from'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-03',
                'title' => 'Unauthorized collection or exaction of money',
                'description' => 'Unauthorized collection or exaction of money, checks, or other instruments as equivalent to money in connection with matters pertaining to the University.',
                'keywords' => ['unauthorized collection', 'collecting money', 'exacting money', 'unauthorized fees', 'extortion', 'collecting without authority', 'was found collecting money', 'caught collecting fees without authority', 'reported collecting money', 'observed collecting payments without', 'seen extorting', 'caught demanding money from'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-04',
                'title' => 'Selling items or soliciting contributions without approval',
                'description' => 'Selling items, engaging in business, or soliciting contributions or donations on campus without prior approval or authority.',
                'keywords' => ['selling on campus', 'peddling', 'soliciting donations', 'selling without permit', 'unauthorized business', 'vending on campus', 'unauthorized selling', 'was found selling', 'caught selling on campus', 'observed selling', 'found peddling', 'reported selling without permit', 'seen soliciting donations without approval', 'caught vending', 'student was selling items', 'found engaging in business on campus'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-05',
                'title' => 'Forging, falsifying, or tampering with official records or documents',
                'description' => 'Forging, falsifying, or tampering with academic or official records or documents of any kind.',
                'keywords' => ['forgery', 'falsification', 'tampering', 'fake document', 'forged records', 'falsified records', 'fake certificate', 'document fraud', 'fake grade', 'tampered document', 'was found with forged', 'caught falsifying', 'reported for tampering', 'found with fake document', 'submitted falsified', 'presented fake', 'caught forging', 'found with tampered records'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-06',
                'title' => 'Perjury',
                'description' => 'Committing perjury, defined as testifying falsely in any administrative proceeding, or knowingly making untruthful statements in documents under oath when such oath is required.',
                'keywords' => ['perjury', 'false testimony', 'lying under oath', 'false statement under oath', 'false affidavit', 'untruthful testimony', 'was found to have lied', 'made false statement', 'testified falsely', 'submitted false sworn statement', 'gave untruthful testimony', 'made untruthful statement under oath', 'found to have committed perjury'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-07',
                'title' => 'Lending or using someone else\'s University ID',
                'description' => 'Lending one\'s University ID to another or using someone else\'s University ID.',
                'keywords' => ['lending id', 'borrowing id', 'using someone else id', 'id lending', 'id borrowing', 'id misuse', 'impersonation with id', 'was caught using another student id', 'found using someone else id', 'caught with id that is not theirs', 'observed lending id', 'reported using another person id', 'found with id belonging to another', 'caught lending university id', 'seen with another student id'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-08',
                'title' => 'Misconduct inside the class, library, laboratory, or campus',
                'description' => 'Misconduct inside the class (F2F or online), library, laboratory, or any place inside the campus, such as disregarding proper class etiquette, disrupting classes deliberately.',
                'keywords' => ['misconduct', 'disruptive', 'loud talking', 'disturbing class', 'class disruption', 'whistling in class', 'heckling', 'noise in class', 'provoking students', 'class misconduct', 'was reported for misconduct', 'observed being disruptive', 'caught making noise inside', 'found disrupting class', 'reported for loud talking', 'seen heckling during class', 'was causing disruption inside', 'caught provoking students', 'reported for disturbing the class', 'observed creating noise in', 'student was being disruptive during', 'found whistling inside classroom'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-09',
                'title' => 'Deliberate disruption of an academic function or school activity',
                'description' => 'Deliberate disruption of an academic function or school activity which tends to create disorder, tumult, breach of peace, or serious disturbance.',
                'keywords' => ['disruption', 'disorder', 'breach of peace', 'disturbing activity', 'disrupting event', 'tumult', 'deliberate disruption', 'serious disturbance', 'was found disrupting', 'caught disrupting event', 'reported for creating disorder', 'observed causing disturbance during', 'found causing breach of peace', 'reported deliberately disrupting', 'was seen disrupting school activity', 'caught causing serious disturbance'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-10',
                'title' => 'Brawl within the University premises or during a school activity',
                'description' => 'Brawl within the University premises or during an academic function or school activity outside the University.',
                'keywords' => ['brawl', 'fighting', 'physical fight', 'fight on campus', 'altercation', 'melee', 'physical altercation', 'rumble', 'was involved in a fight', 'caught fighting', 'observed fighting', 'reported for brawl', 'found fighting on campus', 'seen engaged in a fight', 'was involved in physical altercation', 'caught in the act of fighting', 'involved in a brawl', 'was fighting with another student', 'caught hitting another student', 'found in a brawl', 'reported engaging in a fight'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-11',
                'title' => 'Inflicting physical injuries on another person',
                'description' => 'Inflicting physical injuries on another inside the University premises or during an academic function or school activity outside the University.',
                'keywords' => ['physical injury', 'assault', 'hitting', 'inflicting injury', 'bodily harm', 'physical harm', 'beating', 'punching', 'was caught inflicting injury', 'observed hitting another student', 'reported for assaulting', 'found inflicting physical harm', 'caught beating another student', 'seen punching', 'was reported for causing injury', 'caught causing physical harm to', 'witnessed hitting', 'reported inflicting injuries on'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-12',
                'title' => 'Direct assault upon a member of the University community',
                'description' => 'Direct assault upon the person of any member of the administration, faculty, administrative support personnel (ASP), or any student or person vested with authority.',
                'keywords' => ['direct assault', 'assault on faculty', 'attacking teacher', 'assault on administrator', 'assault on security', 'attacking authority', 'assault staff', 'was caught assaulting faculty', 'reported for assaulting teacher', 'caught attacking security guard', 'found assaulting staff', 'reported directly assaulting', 'was seen attacking faculty member', 'caught in the act of assaulting', 'observed attacking administrator', 'reported for direct assault on'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-13',
                'title' => 'Provocation resulting in a heated verbal or physical confrontation',
                'description' => 'Any provocation that results in a heated verbal or physical confrontation between students and a group of students.',
                'keywords' => ['provocation', 'confrontation', 'heated argument', 'verbal confrontation', 'provoking students', 'inciting fight', 'instigating', 'was found provoking', 'caught provoking other students', 'reported for instigating', 'observed provoking', 'seen instigating a confrontation', 'was provoking', 'caught in a heated argument', 'reported provoking students', 'found to have provoked', 'caused a confrontation by provoking'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-14',
                'title' => 'Possessing or detonating an improvised explosive device (IED)',
                'description' => 'Possessing or detonating an improvised explosive device (IED) inside the University premises.',
                'keywords' => ['explosive', 'ied', 'bomb', 'detonating', 'firecracker', 'pyrotechnics', 'explosive device', 'improvised explosive', 'was found in possession of explosive', 'caught with firecracker', 'found with explosive device', 'reported detonating', 'caught with improvised explosive', 'found carrying explosive', 'observed detonating', 'possession of ied'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-15',
                'title' => 'Vandalism, unhygienic use, or destruction of property',
                'description' => 'Vandalism, unhygienic use, or destruction of property belonging to the University or to any NU personnel, student, or visitor while on campus.',
                'keywords' => ['vandalism', 'destruction of property', 'graffiti', 'defacing', 'damaging property', 'destroying property', 'vandalize', 'damage university property', 'was caught vandalizing', 'found defacing', 'observed damaging', 'caught destroying property', 'reported for vandalism', 'seen writing graffiti', 'found damaging university property', 'caught in the act of vandalizing', 'was found destroying', 'reported for destroying property of'],
                'special_consequence' => 'charged_for_damages',
            ],
            [
                'code' => 'MAJOR-16',
                'title' => 'Preventing circulation of a recognized student publication',
                'description' => 'Preventing the circulation of a recognized student publication by withholding or removing a substantial number of copies from the newsstands.',
                'keywords' => ['student publication', 'withholding publication', 'preventing circulation', 'removing copies', 'newsstand', 'publication suppression', 'was found withholding publication', 'caught removing copies from newsstand', 'reported preventing circulation', 'found preventing distribution of', 'observed removing student publication copies'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-17',
                'title' => 'Gross acts of disrespect',
                'description' => 'Gross acts of disrespect in words or deeds that put the University or any administrator, faculty member, ASP, security guards, maintenance personnel, students, and visitors in ridicule or contempt.',
                'keywords' => ['disrespect', 'contempt', 'ridicule', 'gross disrespect', 'insulting faculty', 'disrespecting authority', 'insubordination', 'disrespecting staff', 'was reported for disrespecting', 'caught being disrespectful to', 'observed disrespecting faculty', 'found ridiculing', 'reported for gross disrespect', 'seen disrespecting security guard', 'was disrespectful to', 'caught insulting', 'reported for insubordination', 'observed showing contempt toward'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-18',
                'title' => 'Acts that bring the name of the University into disrepute',
                'description' => 'Acts that bring the name of the University into disrepute, such as public and malicious imputation of a crime, or of a vice or defect.',
                'keywords' => ['disrepute', 'dishonor', 'defamation', 'malicious imputation', 'discrediting university', 'bringing university bad name', 'contempt of university', 'was reported for bringing university into disrepute', 'found making malicious statements about university', 'caught publicly defaming', 'reported for discrediting the university', 'observed making statements that dishonor'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-19',
                'title' => 'Habitual disregard or willful violation of established policies',
                'description' => 'Habitual disregard or willful violation of established policies, rules, or regulations consisting of three (3) minor offenses of the same kind or nature, or four (4) minor offenses of different kinds or nature.',
                'keywords' => ['habitual disregard', 'willful violation', 'repeated offense', 'habitual offender', 'escalation', 'recurring violation', 'repeated minor offenses', 'four minor offenses', 'this is the third offense', 'this is the fourth offense', 'repeated violation of the same', 'habitual offender of rules', 'found repeatedly violating', 'reported for habitual disregard', 'this is not the first time', 'has committed multiple violations', 'student has prior violations of the same'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-20',
                'title' => 'Refusal to present the University ID when asked by authorities',
                'description' => 'Refusal to present the University ID when asked by authorities.',
                'keywords' => ['refusal id', 'refusing to show id', 'not showing id', 'refusing id presentation', 'id refusal', 'was found refusing to show id', 'refused to present id', 'caught refusing to produce id', 'reported refusing to show id', 'would not present university id', 'refused when asked for id', 'declined to present id', 'refused id presentation to security'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-21',
                'title' => 'Entering any restricted area without proper authority',
                'description' => 'Entering any restricted area within the University without proper authority.',
                'keywords' => ['restricted area', 'unauthorized entry', 'no entry zone', 'entering restricted', 'off-limits area', 'unauthorized access', 'was found inside restricted area', 'caught entering restricted', 'observed entering without authority', 'found in off-limits area', 'reported entering restricted area', 'caught inside area without permit', 'seen entering area they are not authorized to', 'was inside restricted zone'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-22',
                'title' => 'Violation of Student Athlete Dormitory Rules and Regulations',
                'description' => 'Violation of Student Athlete Dormitory Rules and Regulations, amounting to misconduct or major offense as defined in this handbook.',
                'keywords' => ['dormitory', 'dorm violation', 'athlete dorm', 'dormitory rules', 'student athlete dorm', 'dorm misconduct', 'was reported for dormitory violation', 'caught violating dorm rules', 'found violating athlete dormitory', 'reported for misconduct at dorm', 'was in violation of dormitory regulations'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-23',
                'title' => 'Unauthorized bringing in or drinking liquor or alcoholic beverages',
                'description' => 'Unauthorized bringing in or drinking liquor or alcoholic beverages inside the University premises or during off-campus activities.',
                'keywords' => ['alcohol', 'liquor', 'beer', 'alcoholic beverages', 'drinking alcohol', 'bringing alcohol', 'drunk on campus', 'intoxicating liquor', 'was found with alcohol', 'caught drinking alcohol', 'found with beer', 'observed drinking liquor', 'reported bringing in alcohol', 'found in possession of alcoholic', 'caught with alcoholic beverage', 'seen drinking beer on campus', 'found drinking inside campus', 'confiscated alcohol from student'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-24',
                'title' => 'Entering premises or attending activities under the influence of alcohol or prohibited drugs',
                'description' => 'Entering the University premises or attending academic functions and any school activity under the influence of alcohol or prohibited drugs.',
                'keywords' => ['under the influence', 'drunk', 'intoxicated', 'alcohol influence', 'drug influence', 'impaired', 'entering drunk', 'inebriated', 'was found drunk', 'observed entering while intoxicated', 'reported to be under the influence', 'found to be drunk upon entry', 'smelled of alcohol', 'appeared to be intoxicated', 'was seen entering campus while drunk', 'student appeared intoxicated', 'was observed to be under influence of', 'student came in visibly drunk', 'entered campus smelling of alcohol'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-25',
                'title' => 'Bringing in, carrying, possession, or use of deadly weapons',
                'description' => 'Bringing in, carrying, possession, or use of deadly weapons inside the University premises or outside the University during an academic function or school activity.',
                'keywords' => ['deadly weapon', 'firearm', 'knife', 'bladed weapon', 'gun', 'explosive weapon', 'swiss knife', 'ice pick', 'weapon on campus', 'carrying weapon', 'was found carrying a weapon', 'caught in possession of bladed weapon', 'found with knife', 'confiscated weapon from student', 'reported carrying firearm', 'found with deadly weapon', 'caught bringing in weapon', 'confiscated knife from', 'observed carrying bladed', 'possession of deadly weapon confirmed', 'weapon was confiscated from student'],
                'special_consequence' => 'item_confiscation',
            ],
            [
                'code' => 'MAJOR-26',
                'title' => 'Bringing in or smoking cigarettes, e-cigarettes, vapes within University premises',
                'description' => 'Bringing in or smoking cigarettes, e-cigarettes, vapes, and the like within the University premises. (Bringing: Category 2 per offense; Using or Smoking: Category 2 for 1st offense, Category 3 for 2nd offense)',
                'keywords' => ['cigarette', 'vape', 'vaping', 'smoking', 'e-cigarette', 'tobacco', 'nicotine', 'smoke on campus', 'vaping on campus', 'bringing cigarette', 'pod', 'juul', 'vape pen', 'mod', 'was caught smoking', 'observed vaping', 'found smoking on campus', 'caught with vape', 'confiscated vape from student', 'found with cigarette', 'confiscated cigarette', 'seen smoking', 'reported smoking near building', 'caught vaping inside campus', 'was found in possession of vape', 'vape was confiscated from', 'found with e-cigarette', 'observed smoking near', 'caught with tobacco products', 'seen with vape pen', 'student was smoking', 'was vaping inside'],
                'special_consequence' => 'item_confiscation',
            ],
            [
                'code' => 'MAJOR-27',
                'title' => 'Threatening another person',
                'description' => 'Threatening another person with any act amounting to crime, delict or wrong, or with the infliction of any injury or harm to the person\'s honor or integrity.',
                'keywords' => ['threatening', 'threat', 'intimidation', 'threats of harm', 'threatening student', 'threatening faculty', 'menacing', 'was reported for threatening', 'caught threatening another student', 'observed threatening', 'reported making threats against', 'found threatening faculty', 'was threatening', 'made threats toward', 'reported for intimidating', 'was seen threatening', 'caught making threats to'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-28',
                'title' => 'Proselytizing',
                'description' => 'Proselytizing, defined as an attempt to convert another to one\'s faith by attacking or denigrating the other person\'s practices and beliefs, or by offering special inducements.',
                'keywords' => ['proselytizing', 'religious conversion', 'attacking beliefs', 'faith conversion', 'denigrating religion', 'religious harassment', 'was reported for proselytizing', 'caught trying to convert', 'found attempting to convert', 'reported for attacking religious beliefs', 'was seen proselytizing', 'observed denigrating another religion'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-29',
                'title' => 'Gambling inside the University or during school activities',
                'description' => 'Gambling in any form inside the University or during an academic function or school activity outside the University.',
                'keywords' => ['gambling', 'betting', 'cards for money', 'casino', 'illegal gambling', 'wagering', 'gambling on campus', 'was caught gambling', 'found gambling on campus', 'observed gambling', 'reported for gambling', 'caught betting', 'found playing cards for money', 'caught in gambling activity', 'seen wagering', 'was caught in illegal gambling'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-30',
                'title' => 'Acts of subversion',
                'description' => 'Acts of subversion against the University or its legitimate authority.',
                'keywords' => ['subversion', 'subversive act', 'undermining authority', 'act against university', 'subversive activities', 'was found committing subversive', 'reported for acts of subversion', 'caught engaging in subversive activities', 'found undermining university authority'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-31',
                'title' => 'Conviction for a criminal offense involving moral turpitude',
                'description' => 'Conviction before any court for a criminal offense involving moral turpitude and other criminal offenses under the Revised Penal Act and other relevant laws.',
                'keywords' => ['criminal conviction', 'moral turpitude', 'court conviction', 'criminal offense', 'convicted crime', 'was convicted by court', 'found guilty of criminal offense', 'court ruling against student', 'student has criminal conviction', 'reported having conviction for'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-32',
                'title' => 'Hazing or physical injuries for initiation',
                'description' => 'Hazing or physical injuries for initiation, admission, or continuance of membership in any organization, society, or group, whether open or secret.',
                'keywords' => ['hazing', 'initiation rites', 'physical initiation', 'fraternity hazing', 'sorority hazing', 'initiation violence', 'hazing incident', 'was involved in hazing', 'reported for hazing', 'caught hazing', 'found participating in hazing', 'observed hazing incident', 'was present during hazing', 'reported as hazing victim', 'reported as hazing perpetrator', 'was found involved in initiation'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-33',
                'title' => 'Membership in a fraternity, sorority, or unrecognized organization',
                'description' => 'Membership in a fraternity or sorority for students. This includes membership in any unrecognized organization that subscribes to or participates in any violent act.',
                'keywords' => ['fraternity', 'sorority', 'frat member', 'sorority member', 'unrecognized organization', 'frat membership', 'gang membership', 'was found to be a member of fraternity', 'admitted to being frat member', 'reported as fraternity member', 'confirmed fraternity membership', 'found to belong to unrecognized organization'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-34',
                'title' => 'Recruitment of students to join an unrecognized fraternity or sorority',
                'description' => 'Recruitment of fellow students to join the fraternity or sorority that are not recognized by the University.',
                'keywords' => ['recruitment', 'recruiting for frat', 'fraternity recruitment', 'sorority recruitment', 'recruiting members', 'inviting frat', 'was caught recruiting for fraternity', 'found recruiting students', 'reported for recruiting to frat', 'caught inviting to join fraternity', 'observed recruiting for unrecognized organization'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-35',
                'title' => 'Encouraging students to violate their Non-Fraternity Contracts',
                'description' => 'Encouraging students to violate their Non-Fraternity Contracts by inviting them to join a fraternity, sorority, or any organization not recognized by the University.',
                'keywords' => ['non-fraternity contract', 'violating fraternity contract', 'encouraging frat joining', 'anti-frat contract violation', 'was found encouraging students to join', 'caught convincing to join fraternity', 'reported for encouraging violation of non-fraternity contract', 'found persuading students to join frat'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-36',
                'title' => 'Bullying',
                'description' => 'Committing any form of bullying as defined in Sec. 2 Acts of Bullying of RA 10627: The Anti-Bullying Act of 2013.',
                'keywords' => ['bullying', 'bully', 'cyberbullying', 'harassment', 'intimidation', 'verbal abuse', 'physical bullying', 'online bullying', 'anti-bullying', 'was reported for bullying', 'caught bullying', 'observed bullying', 'found bullying another student', 'reported cyberbullying', 'was seen bullying', 'caught harassing another student', 'reported for intimidating', 'complaint of bullying filed against', 'was bullying a classmate', 'found to be bullying'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-37',
                'title' => 'Gender-based sexual harassment',
                'description' => 'Committing any gender-based sexual harassment as defined in RA 11313: The Safe Spaces Act of 2019 (Bawal Bastos Law) and RA 7877: The Anti-Sexual Harassment Act of 1995.',
                'keywords' => ['sexual harassment', 'gender-based harassment', 'bawal bastos', 'safe spaces', 'harassment', 'unwanted advances', 'sexual misconduct', 'gender harassment', 'was reported for sexual harassment', 'caught harassing', 'found committing sexual harassment', 'complaint of harassment filed against', 'reported for gender-based harassment', 'was accused of harassment', 'complaint filed under bawal bastos law', 'found making inappropriate remarks'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-38',
                'title' => 'Unauthorized possession or use of prohibited drugs',
                'description' => 'Unauthorized bringing in, carrying, possession, or use of prohibited drugs or chemicals without a proper prescription inside university premises or during an academic function and off-campus activities.',
                'keywords' => ['drugs', 'prohibited drugs', 'illegal drugs', 'drug possession', 'drug use', 'shabu', 'marijuana', 'narcotics', 'controlled substance', 'drug paraphernalia', 'ra 9165', 'was found in possession of drugs', 'caught with illegal drugs', 'confiscated drugs from student', 'found with marijuana', 'caught with drug paraphernalia', 'reported possession of controlled substance', 'found carrying prohibited drugs', 'drugs were confiscated from', 'student was found with', 'caught in possession of shabu', 'observed with drug paraphernalia'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-39',
                'title' => 'Failure to comply with the University Drug Testing Program',
                'description' => 'Failure to comply with the University Drug Testing Program.',
                'keywords' => ['drug testing', 'drug test refusal', 'failing drug test', 'non-compliance drug test', 'refusing drug test', 'was found refusing drug test', 'reported for not complying with drug testing', 'failed to appear for drug testing', 'refused to undergo drug test', 'did not comply with drug testing program', 'reported non-compliance for drug test'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-40',
                'title' => 'Committing acts of lewdness or any act of immorality',
                'description' => 'Committing acts of lewdness, any act of immorality.',
                'keywords' => ['lewdness', 'immorality', 'indecent act', 'lewd behavior', 'immoral conduct', 'obscene act', 'was reported for lewd behavior', 'caught committing indecent act', 'observed committing act of lewdness', 'reported for immoral conduct', 'found committing obscene act', 'was seen engaging in indecent behavior'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-41',
                'title' => 'Possessing, displaying, or distributing pornographic materials',
                'description' => 'Possessing, displaying, or distributing pornographic materials, including accessing pornographic sites inside the University.',
                'keywords' => ['pornography', 'pornographic materials', 'porn', 'obscene material', 'pornographic site', 'explicit content', 'distributing porn', 'was found with pornographic', 'caught with explicit content', 'found accessing pornographic site', 'reported distributing pornography', 'caught showing pornographic', 'found possessing obscene material', 'was caught with porn', 'found accessing explicit sites on'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-42',
                'title' => 'Committing acts of lasciviousness',
                'description' => 'Committing acts of lasciviousness.',
                'keywords' => ['lasciviousness', 'lascivious act', 'indecent touch', 'sexual touching', 'inappropriate touching', 'lewd touching', 'was reported for lascivious act', 'caught committing lasciviousness', 'reported for inappropriate touching', 'was found committing indecent touch', 'complaint of lascivious act against', 'reported for lewd touching'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-43',
                'title' => 'Making sexual advances',
                'description' => 'Making sexual advances in words or deeds to another student or to any member of the community.',
                'keywords' => ['sexual advances', 'unwanted sexual', 'sexual proposition', 'inappropriate sexual', 'sexual overture', 'sex advances', 'was reported for making sexual advances', 'caught making unwanted advances', 'reported for sexual proposition', 'found making sexual advances toward', 'complaint of sexual advances filed', 'was making advances toward'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-44',
                'title' => 'Public display of affection',
                'description' => 'Public display of attention, such as hugging, kissing and other overt sexual behavior.',
                'keywords' => ['public display of affection', 'pda', 'kissing on campus', 'hugging', 'overt sexual behavior', 'indecent display', 'making out', 'was found kissing', 'caught displaying affection in public', 'observed in public display of affection', 'reported for pda', 'found kissing inside campus', 'caught hugging inappropriately', 'seen making out on campus', 'observed exhibiting pda', 'was kissing in public view', 'caught being affectionate publicly'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-45',
                'title' => 'Violation of computer laboratory policies and guidelines',
                'description' => 'Violation of computer laboratory policies and guidelines as stipulated in Section X.F.',
                'keywords' => ['computer lab', 'computer laboratory', 'lab policy violation', 'computer guidelines', 'it lab rules', 'computer lab violation', 'was found violating computer lab rules', 'caught violating lab policy', 'reported for computer laboratory violation', 'found breaking it lab rules', 'observed violating computer lab guidelines', 'reported violating lab policies'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-46',
                'title' => 'Accessing a university computer or network without authority',
                'description' => 'Accessing a university computer or computer network without authority or beyond authorized access.',
                'keywords' => ['unauthorized access', 'hacking', 'unauthorized computer', 'beyond authorized access', 'computer unauthorized', 'network unauthorized access', 'was found accessing computer without authority', 'caught hacking university network', 'reported for unauthorized access to', 'found using university computer without permission', 'caught accessing network without authorization', 'observed accessing system beyond authorized level'],
                'special_consequence' => null,
            ],
            [
                'code' => 'MAJOR-47',
                'title' => 'Committing acts constituting a computer security breach',
                'description' => 'Committing acts that constitute a computer security breach, such as altering information, changing passwords of others, damaging or destroying information, introducing false information, cyberbullying, preventing authorized use of data, preventing normal operation of computers or networks.',
                'keywords' => ['security breach', 'hacking', 'cyberbullying', 'altering files', 'changing passwords', 'computer virus', 'data tampering', 'network breach', 'cyber attack', 'was found altering computer files', 'caught changing another student password', 'reported for introducing virus', 'found committing security breach', 'caught tampering with data', 'reported for cyberbullying', 'observed accessing and altering', 'found introducing false information into', 'caught in the act of hacking', 'found to have tampered with system'],
                'special_consequence' => null,
            ],
        ];

        $minor = $this->withAdditionalAliases($minor);
        $major = $this->withAdditionalAliases($major);

        $now = now();
        foreach ($minor as $o) {
            DB::table('offense_catalog')->insert(array_merge([
                'code' => $o['code'],
                'offense_type' => 'minor',
                'title' => $o['title'],
                'description' => $o['description'],
                'keywords' => json_encode($o['keywords']),
                'special_consequence' => $o['special_consequence'],
                'created_at' => $now,
            ], $this->handbookReferenceFor($o['code'], 'minor')));
        }
        foreach ($major as $o) {
            DB::table('offense_catalog')->insert(array_merge([
                'code' => $o['code'],
                'offense_type' => 'major',
                'title' => $o['title'],
                'description' => $o['description'],
                'keywords' => json_encode($o['keywords']),
                'special_consequence' => $o['special_consequence'],
                'created_at' => $now,
            ], $this->handbookReferenceFor($o['code'], 'major')));
        }

        $this->command->info('✓ Offense catalog seeded: 15 minor, 47 major offenses.');
        $this->command->info('  Keywords include action phrases for narrative-style incident report matching.');
    }

    private function withAdditionalAliases(array $offenses): array
    {
        $aliases = [
            'MINOR-01' => [
                'walang uniform',
                'hindi naka uniform',
                'naka civilian clothes',
                'not in complete uniform',
            ],
            'MINOR-02' => [
                'walang id',
                'nakalimutan ang id',
                'hindi suot ang id',
                'forgot university id at home',
            ],
            'MINOR-08' => [
                'nagtapon ng basura',
                'nagkalat ng basura',
            ],
            'MAJOR-01' => [
                'nangopya sa exam',
                'gumamit ng kodigo',
                'may dalang kodigo',
                'copied answers from a classmate',
            ],
            'MAJOR-02' => [
                'nagnakaw',
                'kinuha nang walang pahintulot',
                'took a phone without permission',
                'took another student belongings',
            ],
            'MAJOR-10' => [
                'nag away',
                'nagsuntukan',
                'nakipagsuntukan',
            ],
            'MAJOR-11' => [
                'nanakit ng estudyante',
                'sinuntok ang estudyante',
                'physically injured a classmate',
            ],
            'MAJOR-15' => [
                'sinira ang university property',
                'sumulat sa pader',
            ],
            'MAJOR-20' => [
                'tumangging magpakita ng id',
                'ayaw ipakita ang id',
            ],
            'MAJOR-23' => [
                'may dalang alak',
                'umiinom ng alak sa campus',
            ],
            'MAJOR-25' => [
                'may dalang kutsilyo',
                'may dalang baril',
            ],
            'MAJOR-26' => [
                'may dalang vape',
                'nahuling nag vape',
                'nagyoyosi sa campus',
            ],
            'MAJOR-27' => [
                'nagbanta',
                'pinagbantaan ang kaklase',
                'threatened to hurt a classmate',
            ],
            'MAJOR-29' => [
                'nagsusugal',
                'nagpustahan ng pera',
            ],
            'MAJOR-32' => [
                'sapilitang initiation',
                'pisikal na initiation',
            ],
            'MAJOR-36' => [
                'binully ang kaklase',
                'pambubully',
                'pang aapi',
                'intimidated a classmate',
            ],
            'MAJOR-37' => [
                'sekswal na panliligalig',
                'malisyosong sexual remarks',
            ],
            'MAJOR-38' => [
                'may dalang droga',
                'nahuling may marijuana',
            ],
            'MAJOR-46' => [
                'nag hack ng university network',
                'pumasok sa network nang walang pahintulot',
            ],
        ];

        return array_map(function (array $offense) use ($aliases): array {
            $offense['keywords'] = array_values(array_unique(array_merge(
                $offense['keywords'],
                $aliases[$offense['code']] ?? [],
            )));

            return $offense;
        }, $offenses);
    }

    private function handbookReferenceFor(string $code, string $offenseType): array
    {
        if ($offenseType === 'minor') {
            return [
                'handbook_section' => 'Section VI.D',
                'handbook_page' => match ($code) {
                    'MINOR-01', 'MINOR-02' => '33',
                    'MINOR-03' => '33-34',
                    default => '34',
                },
            ];
        }

        $offenseNumber = (int) substr($code, 6);

        return [
            'handbook_section' => 'Section VI.E',
            'handbook_page' => match (true) {
                $offenseNumber <= 3 => '35',
                $offenseNumber <= 16 => '36',
                $offenseNumber <= 30 => '37',
                $offenseNumber <= 45 => '38',
                default => '39',
            },
        ];
    }
}
