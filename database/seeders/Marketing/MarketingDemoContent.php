<?php

namespace Database\Seeders\Marketing;

use App\Enums\DocumentType;

/**
 * Fictional text for the marketing demo. Names, places, measures, and debate
 * lines are invented and must stay consistent with each other: the transcript
 * quotes the numbers in the measures, and the tallies match the ballots.
 */
final class MarketingDemoContent
{
    public const ORGANIZATION = 'Sangguniang Panlalawigan';

    public const SHORT_NAME = 'SP';

    public const LOCALITY = 'Province of Demo';

    /**
     * Seated members added on top of the canonical UserSeeder accounts, keyed
     * by the local part of their `@sentria.test` email.
     *
     * @return array<string, array<string, string|null>>
     */
    public static function members(): array
    {
        return [
            'pascual' => ['Ernesto', 'Villaroman', 'Pascual', 'Board Member', '1st District'],
            'castillo' => ['Lourdes', 'Tan', 'Castillo', 'Board Member', '1st District'],
            'delossantos' => ['Ramon', 'Garcia', 'Delos Santos', 'Board Member', '2nd District'],
            'soriano' => ['Milagros', 'Dela Paz', 'Soriano', 'Board Member', '2nd District'],
            'macaraeg' => ['Antonio', 'Ramos', 'Macaraeg', 'Board Member', '3rd District'],
            'ilagan' => ['Rosario', 'Mercado', 'Ilagan', 'Board Member', '3rd District'],
            'buenaventura' => ['Victor', 'Samonte', 'Buenaventura', 'Board Member', '4th District'],
            'evangelista' => ['Carmelita', 'Javier', 'Evangelista', 'Board Member', '4th District'],
            'abad' => ['Jerome', 'Quiambao', 'Abad', 'Ex Officio Member, Liga ng mga Barangay', null],
            'ramirez' => ['Kyla', 'Domingo', 'Ramirez', 'Ex Officio Member, SK Provincial Federation', null],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function measures(int $year): array
    {
        return [
            'scholarship' => [
                'type' => DocumentType::ProposedOrdinance,
                'reference' => sprintf('PO-%d-00041', $year),
                'number_label' => sprintf('Proposed Ordinance No. %d-041', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'castillo',
                'co_authors' => ['ilagan', 'ramirez'],
                'title' => 'An Ordinance Establishing the Provincial Scholarship Program for Indigent Students of the Province of Demo and Appropriating Funds Therefor',
                'abstract' => 'Creates a provincial scholarship for indigent college and technical-vocational students, sets eligibility and benefits, creates a Provincial Scholarship Board, and appropriates PHP 12,000,000.00 for the first year for about 400 scholars.',
                'tags' => ['education', 'budget'],
                'status' => 'reading-deliberation',
                'explanatory_note' => [
                    'Many qualified students in the province stop after senior high school because their families cannot cover the costs that free tuition does not reach: miscellaneous fees, books, transport, and board. This measure closes that gap for students from low-income families.',
                    'The program is funded from the General Fund, awarded through a board that includes the Committee on Education and civil society, and tied to academic standing so that slots go to students who keep up their grades.',
                ],
                'enacting_clause' => 'Be it ordained by the Sangguniang Panlalawigan of the Province of Demo, in session assembled, that:',
                'sections' => [
                    ['heading' => 'Section 1. Title.', 'body' => 'This Ordinance shall be known as the "Provincial Scholarship Program for Indigent Students Ordinance of '.$year.'."'],
                    ['heading' => 'Section 2. Declaration of Policy.', 'body' => 'It is the policy of the Province to make tertiary and technical-vocational education accessible to deserving students from low-income families, in the exercise of its powers under the general welfare clause.'],
                    ['heading' => 'Section 3. Definition of Terms.', 'body' => 'As used in this Ordinance:', 'items' => [
                        'Indigent student refers to a student whose combined annual family income does not exceed the provincial poverty threshold, as certified by the Provincial Social Welfare and Development Office;',
                        'Scholar refers to an indigent student awarded a scholarship under this Ordinance;',
                        'Board refers to the Provincial Scholarship Board created under Section 6.',
                    ]],
                    ['heading' => 'Section 4. Eligibility.', 'body' => 'An applicant must:', 'items' => [
                        'be a Filipino citizen and a resident of the Province for at least three (3) years;',
                        'be an indigent student as defined in Section 3;',
                        'have a general weighted average of at least 85% or its equivalent in the last completed school year;',
                        'be enrolled in, or accepted by, a state university, local college, or accredited technical-vocational institution in the Province;',
                        'not hold another full scholarship funded by government.',
                    ]],
                    ['heading' => 'Section 5. Benefits.', 'body' => 'Each scholar shall receive, per semester, the tuition and miscellaneous fees not covered by free higher education, a book allowance of PHP 2,000.00, and a stipend of PHP 6,000.00.'],
                    ['heading' => 'Section 6. Provincial Scholarship Board.', 'body' => 'The Board shall be composed of the Provincial Governor or a duly designated representative, as Chair; the Chair of the Committee on Education of the Sangguniang Panlalawigan; the Provincial Social Welfare and Development Officer; a representative of the Schools Division Office; and one representative of an accredited civil society organization.'],
                    ['heading' => 'Section 7. Slots and Distribution.', 'body' => 'Four hundred (400) slots shall be awarded in the first year, distributed among the twelve (12) municipalities of the Province in proportion to population, with no fewer than twenty (20) slots for each municipality.'],
                    ['heading' => 'Section 8. Appropriation.', 'body' => 'The amount of Twelve Million Pesos (PHP 12,000,000.00) is hereby appropriated from the General Fund for the first year of implementation. Thereafter, the amount necessary shall be included in the annual budget of the Province.'],
                    ['heading' => 'Section 9. Continuing Eligibility.', 'body' => 'A scholar must maintain a general weighted average of at least 83% with no failing grade. A scholar who falls below this standard shall be placed on probation for one (1) semester before the scholarship is withdrawn.'],
                    ['heading' => 'Section 10. Implementing Rules.', 'body' => 'The Board shall issue the implementing rules and regulations within sixty (60) days from the effectivity of this Ordinance.'],
                    ['heading' => 'Section 11. Separability Clause.', 'body' => 'If any provision of this Ordinance is declared invalid, the remaining provisions shall continue in full force and effect.'],
                    ['heading' => 'Section 12. Effectivity.', 'body' => 'This Ordinance shall take effect fifteen (15) days after its publication in a newspaper of general circulation in the Province.'],
                ],
            ],

            'road_moa' => [
                'type' => DocumentType::ProposedResolution,
                'reference' => sprintf('PR-%d-00117', $year),
                'number_label' => sprintf('Proposed Resolution No. %d-117', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'pascual',
                'co_authors' => ['buenaventura'],
                'title' => 'A Resolution Authorizing the Provincial Governor to Enter into and Sign a Memorandum of Agreement with the Department of Public Works and Highways for the Rehabilitation of the 6.4-Kilometer San Isidro to Maligaya Provincial Road',
                'abstract' => 'Authorizes the Provincial Governor to sign a memorandum of agreement with the DPWH district office for the rehabilitation of the San Isidro to Maligaya provincial road, with the Province providing right-of-way and maintenance after turnover.',
                'tags' => ['infrastructure'],
                'status' => 'reading-deliberation',
                'whereas' => [
                    'the San Isidro to Maligaya provincial road is the main farm-to-market route for four municipalities and is impassable to heavy vehicles during the rainy season;',
                    'the Department of Public Works and Highways has offered to fund and carry out the rehabilitation under a memorandum of agreement with the Province;',
                    'the Province shall provide the road right-of-way and assume maintenance after turnover of the completed road;',
                ],
                'enacting_clause' => 'NOW, THEREFORE, on motion duly seconded, be it resolved, as it is hereby resolved, by the Sangguniang Panlalawigan of the Province of Demo, in session assembled:',
                'sections' => [
                    ['heading' => '', 'body' => 'To authorize the Provincial Governor to enter into and sign, for and on behalf of the Province, a memorandum of agreement with the Department of Public Works and Highways for the rehabilitation of the 6.4-kilometer San Isidro to Maligaya provincial road;'],
                    ['heading' => '', 'body' => 'RESOLVED FURTHER, that copies of this Resolution be furnished the Provincial Governor, the Provincial Engineering Office, and the Department of Public Works and Highways district office for their information and appropriate action.'],
                ],
            ],

            'tricycle' => [
                'type' => DocumentType::ProposedOrdinance,
                'reference' => sprintf('PO-%d-00036', $year),
                'number_label' => sprintf('Proposed Ordinance No. %d-036', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'soriano',
                'co_authors' => ['delossantos'],
                'title' => 'An Ordinance Regulating Tricycles on Provincial Roads through a Route Permit System and Providing Penalties for Violations',
                'abstract' => 'Requires an annual provincial route permit for tricycles using provincial roads, waives the fee for units with a valid municipal franchise, sets safety requirements, and prescribes graduated penalties.',
                'tags' => ['infrastructure', 'peace-and-order'],
                'status' => 'reading-deliberation',
                'explanatory_note' => [
                    'Tricycles increasingly use provincial roads between municipalities, where no single municipal franchise applies. This measure sets one route permit for those roads without adding a second fee for operators who already hold a municipal franchise.',
                ],
                'enacting_clause' => 'Be it ordained by the Sangguniang Panlalawigan of the Province of Demo, in session assembled, that:',
                'sections' => [
                    ['heading' => 'Section 1. Title.', 'body' => 'This Ordinance shall be known as the "Provincial Road Tricycle Regulation Ordinance."'],
                    ['heading' => 'Section 2. Coverage.', 'body' => 'This Ordinance covers tricycles operating along provincial roads. It does not amend or replace municipal tricycle franchises.'],
                    ['heading' => 'Section 3. Route Permit.', 'body' => 'No tricycle shall operate along a provincial road without a route permit issued by the Provincial Engineering Office. One permit shall be issued per unit and renewed every year.'],
                    ['heading' => 'Section 4. Safety Requirements.', 'body' => 'Every unit shall have working headlights, tail lights, and reflectors, shall display its permit number, and shall carry no more passengers than its approved seating capacity.'],
                    ['heading' => 'Section 5. Fees.', 'body' => 'The annual route permit fee is Two Hundred Pesos (PHP 200.00). The fee is waived for any unit that holds a valid municipal franchise.'],
                    ['heading' => 'Section 6. Penalties.', 'body' => 'Violations shall be penalized as follows:', 'items' => [
                        'First offense: a fine of PHP 1,000.00;',
                        'Second offense: a fine of PHP 2,500.00;',
                        'Third offense: a fine of PHP 5,000.00 and suspension of the route permit of the unit for six (6) months.',
                    ]],
                    ['heading' => 'Section 7. Effectivity.', 'body' => 'This Ordinance shall take effect fifteen (15) days after its publication in a newspaper of general circulation in the Province.'],
                ],
            ],

            'watershed' => [
                'type' => DocumentType::ProposedOrdinance,
                'reference' => sprintf('PO-%d-00047', $year),
                'number_label' => sprintf('Proposed Ordinance No. %d-047', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'ilagan',
                'co_authors' => [],
                'title' => 'An Ordinance Prescribing Guidelines for the Protection and Management of the Mount Balantoy Watershed',
                'abstract' => 'Designates protection and buffer zones in the Mount Balantoy watershed, restricts quarrying and land conversion in the protection zone, and creates a multi-sector watershed management council.',
                'tags' => ['environment'],
                'status' => 'committee-review',
                'committee' => 'Committee on Environmental Protection, Ecology, and Natural Resources',
                'explanatory_note' => [
                    'The Mount Balantoy watershed supplies water to three municipalities. This measure sets clear zones and rules so that its forest cover and springs are protected.',
                ],
                'enacting_clause' => 'Be it ordained by the Sangguniang Panlalawigan of the Province of Demo, in session assembled, that:',
                'sections' => [
                    ['heading' => 'Section 1. Title.', 'body' => 'This Ordinance shall be known as the "Mount Balantoy Watershed Protection Ordinance."'],
                    ['heading' => 'Section 2. Zones.', 'body' => 'The watershed is divided into a protection zone and a buffer zone, as shown in the map annexed to this Ordinance.'],
                    ['heading' => 'Section 3. Prohibited Acts.', 'body' => 'Quarrying, tree cutting without a permit, and conversion of land to non-forest use are prohibited in the protection zone.'],
                    ['heading' => 'Section 4. Watershed Management Council.', 'body' => 'A council composed of the Provincial Environment and Natural Resources Officer, the mayors of the three host municipalities, and two representatives of accredited people\'s organizations shall oversee implementation.'],
                    ['heading' => 'Section 5. Effectivity.', 'body' => 'This Ordinance shall take effect fifteen (15) days after its publication in a newspaper of general circulation in the Province.'],
                ],
            ],

            'commendation' => [
                'type' => DocumentType::Resolution,
                'reference' => sprintf('RES-%d-00108', $year),
                'number_label' => sprintf('Resolution No. %d-108', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'macaraeg',
                'co_authors' => ['ilagan', 'pascual'],
                'title' => 'A Resolution Commending the Provincial Disaster Risk Reduction and Management Office and Its Partner Rescue Volunteers for Their Response during the Recent Flooding in the Province of Demo',
                'abstract' => 'Commends the PDRRMO and volunteer rescue groups for three days of continuous rescue and relief operations during the recent flooding.',
                'tags' => ['peace-and-order'],
                'status' => 'public-publication',
                'resolution_number' => sprintf('RES-%d-108', $year),
                'resolution_category' => 'commendation',
                'whereas' => [
                    'heavy rains caused flooding in several municipalities of the Province, displacing families in low-lying barangays;',
                    'the Provincial Disaster Risk Reduction and Management Office, together with volunteer rescue groups, carried out rescue and relief operations for three straight days;',
                    'no loss of life was recorded in the areas covered by these operations;',
                ],
                'enacting_clause' => 'NOW, THEREFORE, on motion duly seconded, be it resolved, as it is hereby resolved, by the Sangguniang Panlalawigan of the Province of Demo, in session assembled:',
                'sections' => [
                    ['heading' => '', 'body' => 'To commend the Provincial Disaster Risk Reduction and Management Office and its partner rescue volunteers for their dedication and service to the people of the Province;'],
                    ['heading' => '', 'body' => 'RESOLVED FURTHER, that a copy of this Resolution be furnished the Provincial Disaster Risk Reduction and Management Office.'],
                ],
                'publication_summary' => 'The Sangguniang Panlalawigan commends the PDRRMO and volunteer rescue groups for their rescue and relief work during the recent flooding.',
            ],

            'drrm_plan' => [
                'type' => DocumentType::Ordinance,
                'reference' => sprintf('ORD-%d-00009', $year),
                'number_label' => sprintf('Provincial Ordinance No. %d-009', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'buenaventura',
                'co_authors' => ['macaraeg'],
                'title' => 'An Ordinance Adopting the Provincial Disaster Risk Reduction and Management Plan for the Next Three Years',
                'abstract' => 'Adopts the three-year Provincial DRRM Plan, including hazard maps, evacuation center standards, and the use of the provincial DRRM fund.',
                'tags' => ['peace-and-order', 'budget'],
                'status' => 'public-publication',
                'ordinance_number' => '009',
                'enacted_months_ago' => 6,
                'enacting_clause' => 'Be it ordained by the Sangguniang Panlalawigan of the Province of Demo, in session assembled, that:',
                'sections' => [
                    ['heading' => 'Section 1. Adoption.', 'body' => 'The Provincial Disaster Risk Reduction and Management Plan, attached as Annex A, is hereby adopted for the next three (3) years.'],
                    ['heading' => 'Section 2. Evacuation Centers.', 'body' => 'Every municipality shall maintain at least one evacuation center that meets the standards set in the Plan.'],
                    ['heading' => 'Section 3. Effectivity.', 'body' => 'This Ordinance shall take effect fifteen (15) days after its publication in a newspaper of general circulation in the Province.'],
                ],
                'publication_summary' => 'Adopts the three-year Provincial Disaster Risk Reduction and Management Plan.',
            ],

            'sports_fees' => [
                'type' => DocumentType::Ordinance,
                'reference' => sprintf('ORD-%d-00014', $year),
                'number_label' => sprintf('Provincial Ordinance No. %d-014', $year),
                'authorship_label' => 'Introduced by',
                'author' => 'ramirez',
                'co_authors' => ['abad'],
                'title' => 'An Ordinance Setting the Fees and Charges for the Use of Provincial Sports Facilities',
                'abstract' => 'Sets hourly fees for the provincial gymnasium, oval, and swimming pool, with free use for public school athletes and registered youth leagues.',
                'tags' => ['budget'],
                'status' => 'public-publication',
                'ordinance_number' => '014',
                'enacted_months_ago' => 4,
                'enacting_clause' => 'Be it ordained by the Sangguniang Panlalawigan of the Province of Demo, in session assembled, that:',
                'sections' => [
                    ['heading' => 'Section 1. Schedule of Fees.', 'body' => 'The use of the provincial gymnasium shall be charged PHP 500.00 per hour, the oval PHP 300.00 per hour, and the swimming pool PHP 50.00 per person per session.'],
                    ['heading' => 'Section 2. Exemptions.', 'body' => 'Public school athletes and registered youth leagues shall use the facilities free of charge, subject to scheduling.'],
                    ['heading' => 'Section 3. Effectivity.', 'body' => 'This Ordinance shall take effect fifteen (15) days after its publication in a newspaper of general circulation in the Province.'],
                ],
                'publication_summary' => 'Sets fees for the provincial gymnasium, oval, and pool, with free use for public school athletes and youth leagues.',
            ],
        ];
    }

    /**
     * Debate on the live item, as the chamber pipeline would store it. Lines
     * with a null speaker came off the mixer and wait for the secretariat to
     * assign them. Offsets are seconds from the start of the sitting.
     *
     * @return list<array{0: float, 1: string|null, 2: string, 3: float}>
     */
    public static function liveTranscript(int $year): array
    {
        return [
            [2890.0, 'presiding', sprintf('The body will now consider Proposed Ordinance No. %d-041 on second reading. The chair recognizes the sponsor, the Honorable Castillo.', $year), 0.96],
            [2903.5, 'castillo', 'Thank you, Madam Presiding Officer. This measure creates a scholarship for indigent students in all twelve municipalities of the province.', 0.94],
            [2918.0, 'castillo', 'Section 4 sets eligibility: three years of residency, a family income below the provincial poverty threshold, and a general weighted average of at least 85.', 0.92],
            [2941.0, null, 'Will the sponsor yield to a few questions on the funding source?', 0.88],
            [2946.5, 'castillo', 'Gladly. The sponsor yields.', 0.95],
            [2951.0, null, 'How many slots are funded in the first year, and is the amount already in the annual budget?', 0.34],
            [2963.0, 'castillo', 'Section 7 funds four hundred slots in the first year. Section 8 appropriates twelve million pesos from the General Fund, and after that it goes into the annual budget.', 0.93],
            [2980.0, 'gallery', 'For the Provincial Scholarship Board: we can publish the slot list on the provincial website once the ordinance is enacted.', 0.81],
            [2992.0, null, 'Is there a minimum number of slots for the smaller municipalities?', 0.86],
            [2998.0, 'castillo', 'Yes. No municipality receives fewer than twenty slots, whatever its population.', 0.95],
        ];
    }

    /**
     * Completed transcript of the previous sitting. Every line is attributed;
     * one line keeps its machine original because the secretariat corrected it.
     *
     * @return list<array<string, mixed>>
     */
    public static function adjournedTranscript(int $year): array
    {
        return [
            ['start' => 1520.0, 'speaker' => 'presiding', 'confidence' => 0.95, 'text' => sprintf('The body will now take up Proposed Ordinance No. %d-036 on second reading. The chair recognizes the author, the Honorable Soriano.', $year)],
            ['start' => 1534.0, 'speaker' => 'soriano', 'confidence' => 0.93, 'text' => 'Thank you, Madam Presiding Officer. This ordinance covers tricycles that use provincial roads. Municipal franchises are not touched.'],
            ['start' => 1549.5, 'speaker' => 'soriano', 'confidence' => 0.91, 'text' => 'What changes is the route permit. One permit per unit, issued by the Provincial Engineering Office, renewed every year.'],
            ['start' => 1566.0, 'speaker' => 'delossantos', 'confidence' => 0.9, 'text' => 'Will the author yield? Many of our drivers already pay a municipal franchise fee. Is this a second fee?'],
            ['start' => 1579.0, 'speaker' => 'soriano', 'confidence' => 0.94, 'text' => 'The permit fee is two hundred pesos a year, and Section 5 waives it for any unit that already holds a valid municipal franchise.'],
            ['start' => 1596.5, 'speaker' => 'gallery', 'confidence' => 0.84, 'text' => 'For the Provincial Engineering Office: we can process permits in the municipal halls during the first sixty days.'],
            ['start' => 1611.0, 'speaker' => 'macaraeg', 'confidence' => 0.36, 'text' => 'On penalties, is the third offense suspension for the unit or for the driver?'],
            ['start' => 1620.5, 'speaker' => 'soriano', 'confidence' => 0.92, 'text' => 'For the route permit of the unit, for six months. The driver\'s license is not affected.'],
            ['start' => 1640.0, 'speaker' => 'pascual', 'confidence' => 0.58, 'text' => sprintf('I move that we approve Proposed Ordinance No. %d-036 on second reading, as amended.', $year), 'original_text' => 'I move that we approve proposed ordinance number thirty six on second reading as I mended.'],
            ['start' => 1648.0, 'speaker' => 'castillo', 'confidence' => 0.97, 'text' => 'Seconded.'],
            ['start' => 1652.5, 'speaker' => 'presiding', 'confidence' => 0.95, 'text' => 'There is a motion duly seconded. Secretariat, please open the voting.'],
            ['start' => 1731.0, 'speaker' => 'presiding', 'confidence' => 0.94, 'text' => 'With nine in favor, two against, and one abstention, the ordinance is approved on second reading.'],
            ['start' => 1760.0, 'speaker' => 'presiding', 'confidence' => 0.95, 'text' => sprintf('Next is Proposed Resolution No. %d-108, commending the PDRRMO. The chair recognizes the Honorable Macaraeg.', $year)],
            ['start' => 1771.5, 'speaker' => 'macaraeg', 'confidence' => 0.92, 'text' => 'Madam Presiding Officer, our rescue teams worked for three straight days during the flooding. This resolution puts that on the record.'],
            ['start' => 1790.0, 'speaker' => 'ilagan', 'confidence' => 0.93, 'text' => 'I move to adopt the resolution.'],
            ['start' => 1793.0, 'speaker' => 'soriano', 'confidence' => 0.96, 'text' => 'Seconded.'],
            ['start' => 1862.0, 'speaker' => 'presiding', 'confidence' => 0.94, 'text' => 'Eleven in favor, none against, one abstention. The resolution is adopted.'],
        ];
    }

    /**
     * Camera-ready AI draft for the adjourned sitting. Vote numbers match the
     * ballots: tricycle 9-2-1, commendation 11-0-1. The presiding officer does
     * not vote. Official tallies come from the votes table, not the audio.
     */
    public static function adjournedMinutesDraft(int $year, string $date): string
    {
        $banner = 'AI-GENERATED DRAFT — REQUIRES SECRETARIAT REVIEW';

        return <<<DRAFT
# Minutes — 37th Regular Session

> {$banner}

## Session Information
- Session number: RS-{$year}-00037
- Date: {$date}
- Venue: Session Hall, Provincial Capitol
- Presiding Officer: Teresita Villanueva
- Secretary: Joselito Fernandez
- Actual start: 09:05
- Adjournment: 12:20

## Attendance
- Teresita Villanueva — present
- Rafael Dizon — present
- Corazon Manalo — present
- Danilo Salazar — present
- Ernesto Pascual — present
- Lourdes Castillo — present
- Ramon Delos Santos — present
- Milagros Soriano — present
- Antonio Macaraeg — present
- Rosario Ilagan — present
- Victor Buenaventura — on-official-business (Attending a regional development council meeting.)
- Carmelita Evangelista — present
- Jerome Abad — late
- Kyla Ramirez — present

## Proceedings
- 09:00–09:10 1. Call to Order
- 09:00–09:10 2. Invocation
- 09:00–09:10 3. Roll Call
  - Quorum declared 09:12
- 09:00–09:10 4. Reading and Consideration of the Minutes
- 09:00–09:10 5. Privilege Hour
- 09:00–09:10 6. Reference of Business
- 09:00–09:10 7. Calendar of Business
- 09:00–09:10 7.1 Unfinished Business
- 09:00–09:10 7.2 Business for the Day
- 09:05–09:37 7.2.1 An Ordinance Regulating Tricycles on Provincial Roads through a Route Permit System and Providing Penalties for Violations
  - The author, Honorable Soriano, explained that the route permit applies to provincial roads and does not replace a municipal franchise. Section 5 waives the PHP 200.00 fee for any unit that already holds a valid municipal franchise.
  - 09:27 Motion moved — see Motions on Record
  - 09:27 Motion seconded — see Motions on Record
  - 09:27 Vote opened (Round 1) — see Official Vote Results
  - 09:29 Vote closed (Round 1) — see Official Vote Results
- 09:30–09:40 7.2.2 A Resolution Commending the Provincial Disaster Risk Reduction and Management Office and Its Partner Rescue Volunteers for Their Response during the Recent Flooding in the Province of Demo
  - Honorable Macaraeg placed the three days of rescue and relief work on the record.
  - 09:30 Motion moved — see Motions on Record
  - 09:30 Motion seconded — see Motions on Record
  - 09:30 Vote opened (Round 1) — see Official Vote Results
  - 09:32 Vote closed (Round 1) — see Official Vote Results
- 09:00–09:10 8. Business on Third and Final Reading
- 09:00–09:10 9. Other Matters / Announcements
- 09:00–09:10 10. Adjournment

## Motions on Record
- 09:27 I move that Proposed Ordinance No. {$year}-036 be approved on second reading, as amended. moved by Ernesto Pascual, seconded by Lourdes Castillo at 09:27, status carried
- 09:30 I move that Proposed Resolution No. {$year}-108 be adopted. moved by Rosario Ilagan, seconded by Milagros Soriano at 09:30, status carried

## Official Vote Results (authoritative)
### An Ordinance Regulating Tricycles on Provincial Roads through a Route Permit System and Providing Penalties for Violations (Round 1)
Vote opened: 09:27
Vote closed: 09:29
YES: 9 NO: 2 ABSTAIN: 1 INHIBIT: 0

### A Resolution Commending the Provincial Disaster Risk Reduction and Management Office and Its Partner Rescue Volunteers for Their Response during the Recent Flooding in the Province of Demo (Round 1)
Vote opened: 09:30
Vote closed: 09:32
YES: 11 NO: 0 ABSTAIN: 1 INHIBIT: 0
DRAFT;
    }

    /**
     * Owner-only note on the live scholarship measure, shown in My notes on
     * the member tablet. It is not the transcript and not the minutes.
     */
    public static function memberScholarshipNote(): string
    {
        return 'Section 7: twenty-slot floor for the smaller municipalities. Ask the sponsor whether the stipend is per semester.';
    }
}
