# Secretariat Manual

## Role purpose

Manage documents, agendas, sessions, minutes workflow, and publications.

## Session workflow

1. Create session → prepare agenda → schedule session.
2. During session: support floor secretariat view; record attendance; advance agenda. Seated members are marked present when they open the paperless member floor; use roll call to override (late, excused, official business, absent). When a member is recognized to move, record the spoken wording (attributed to that member).
3. Open/close electronic voting when directed by presiding officer.
4. After adjournment: generate AI **draft** minutes → review → approve → finalize.

**AI drafts are never final** until a human approves.

## Documents

- Register incoming measures; manage versions and committee referrals.
- Proposed ordinances and resolutions follow first reading (title only), committee referral, committee report, Committee on Rules marking the measure ready for the next reading, second reading, final form, third reading, then LCE / SP / posting on the ordinance record. Secretariat places ready measures on a sitting when preparing that session's agenda.
- Require the filing checklist before register: written measure, number/title, enacting clause, proposed effectivity, explanatory note (ordinances), signed author.
- Unfavorable committee action lays the measure on the table (archive) and notifies the proponent.
- Record the Sanggunian seal after passage. After real-life (or floor) approval, record the official number on Legislation; the register is not a continuation of the document buttons.
- Set confidentiality correctly; use publication workflow for public release. Posting clocks are 5 days after approval; effectivity is usually 10 days after posting.
- Uploads pass malware scan when production adapter is enabled.

## Session floor

- Opening the paperless member floor marks that seated member **present**. Roll call on `/sessions/{id}/attendance` remains the override and does not get overwritten by a later floor reload.
- Members marked `on-official-business` (abroad or similarly excused) do **not** count toward quorum. Only `present` and `late` count. The system still does not declare quorum — the presiding officer does.
- Live transcription belongs to the session transcript channel, not the document file. Mixer mix is the default: unassigned lines appear in the secretariat discussion inbox; assign each to a seated member or Gallery. Per-seat capture still uses the chamber map at `/settings/chamber-channels`. Recording must be enabled on the sitting. Setup: [chamber-microphones.md](chamber-microphones.md).
- When a member taps **Raise a motion**, a live dock appears on every floor surface. After the presiding officer recognizes them, record the spoken wording here — it is attributed to that member. There is no member text form.
- First reading shows title only on the floor. Full text returns on second and third reading.

## Minutes

- Run draft generation; edit in review states.
- Official vote results in minutes come from the **votes table**, not AI transcription alone.

## Publications

- Only explicitly published records appear on `/portal`.
- Unpublished content returns **404** to anonymous users (by design).

## Backup awareness

Coordinate with ICT for backup schedules. After major session days, confirm backup success on `/admin/monitoring`.

## You do NOT

- Declare quorum met for legal purposes — system shows status; presiding officer decides.
- Publish without proper review transitions.
