"""High-precision handbook boundary guardrails for the multilingual classifier.

These rules do not replace the statistical classifier. They only resolve a small
set of explicit policy boundaries after the ensemble has produced its ranking.
"""

from __future__ import annotations

import re


GUARDRAIL_VERSION = "2026-09-16-v11"


_MINOR_LABEL = re.compile(r"^MINOR-\d+$")

# Deadly-weapon vocabulary, including the plain lay terms an officer types under
# pressure. The trained ensemble never saw the bare word "gun" in any narrative,
# so these terms cannot be left to the statistical ranking alone.
_WEAPON_NOUNS = (
    r"(?:"
    r"gun|guns|handgun|handguns|shotgun|rifle|pistol|revolver|armalite|paltik|sumpak|"
    r"firearm|firearms|baril|bala|bullet|bullets|live ammunition|"
    r"knife|knives|kutsilyo|patalim|balisong|switchblade|machete|itak|bolo|karambit|"
    r"dagger|punyal|cleaver|sundang|ice[- ]?pick|icepick|razor blade|bladed weapon|"
    r"metal knuckles|brass knuckles|knuckle duster|nunchaku|stun gun|taser|"
    r"slingshot|slingshots|tirador|indian pana|steel pellets?|steel balls?|ball bearings?|"
    r"explosive|explosives|grenade|molotov|pillbox|"
    r"deadly weapon|weapon|weapons|armas|sandata"
    r")"
)

_DEADLY_WEAPON_TERMS = re.compile(r"\b" + _WEAPON_NOUNS + r"\b")

# "Gun" and "blade" carry ordinary non-weapon senses that must not be escalated.
# The benign qualifier may sit one word away from the noun ("toy water pistol",
# "plastic prop knife"), so the qualifier group allows a single filler word.
_BENIGN_QUALIFIER = (
    r"(?:glue|hot[- ]?melt|water|squirt|nerf|toy|replica|prop|foam|paintball|"
    r"plastic|rubber|inflatable|cardboard|dummy|training|wooden)"
)
_QUALIFIABLE_WEAPON_NOUNS = (
    r"(?:guns?|pistols?|rifles?|revolvers?|firearms?|knife|knives|swords?|"
    r"grenades?|baril|sandata)"
)

_NON_WEAPON_SENSES = re.compile(
    r"\b" + _BENIGN_QUALIFIER + r"(?:\s+\w+)?\s+" + _QUALIFIABLE_WEAPON_NOUNS + r"\b"
    r"|\bguns?\s+(?:control|violence|ban|law|laws|policy|reform|safety)\b"
    r"|\bjump\w*\s+the\s+gun\b"
    r"|\bshoulder\s+blades?\b|\bblade\s+servers?\b"
)

# A weapon depicted on clothing or in an image is not a weapon brought on campus.
_DEPICTION_NOUNS = (
    r"(?:graphic|graphics|print|printed|design|drawing|drawn|image|picture|photo|"
    r"logo|sticker|tattoo|emoji|wallpaper|screensaver|poster|artwork|illustration|"
    r"sketch|larawan|guhit|disenyo)"
)

_WEAPON_DEPICTION = re.compile(
    r"\b" + _DEPICTION_NOUNS + r"\b.{0,60}?\b" + _WEAPON_NOUNS + r"\b"
    r"|\b" + _WEAPON_NOUNS + r"\b.{0,60}?\b" + _DEPICTION_NOUNS + r"\b"
)

# Narratives that expressly record the absence of a weapon must not be escalated.
# Every alternative deliberately spans the weapon term itself, so that a report
# such as "without an ID but carrying a knife" still escalates.
_WEAPON_ABSENCE = re.compile(
    r"\b(?:no|without|walang|wala)\b(?:\s+\w+){0,3}\s+\b" + _WEAPON_NOUNS + r"\b"
    r"|\b(?:did not|didn't|hindi)\b(?:\s+\w+){0,3}\s+"
    r"\b(?:bring|brought|bringing|carry|carried|carrying|possess|nagdala|magdala|dala)\b"
    r"(?:\s+\w+){0,3}\s+\b" + _WEAPON_NOUNS + r"\b"
    r"|\b" + _WEAPON_NOUNS + r"\b(?:\s+\w+){0,6}\s+"
    r"\b(?:was not found|were not found|not found|hindi natagpuan|walang nakita)\b"
)

_BENIGN_WEAPON_CONTEXTS = (
    (_NON_WEAPON_SENSES, "non_weapon_sense_of_the_term"),
    (_WEAPON_DEPICTION, "weapon_depicted_not_carried"),
    (_WEAPON_ABSENCE, "recorded_absence_of_a_weapon"),
)


def _benign_weapon_spans(value: str) -> list[tuple[int, int]]:
    return [
        match.span()
        for pattern, _ in _BENIGN_WEAPON_CONTEXTS
        for match in pattern.finditer(value)
    ]


def _has_unexplained_weapon_reference(value: str) -> bool:
    """True when a weapon term appears outside every benign context.

    Matching by span rather than by presence is what lets a narrative such as
    "used a glue gun and also carried a knife" escalate on the knife while
    "used a glue gun" alone does not.
    """
    benign_spans = _benign_weapon_spans(value)

    return any(
        not any(start <= match.start() and match.end() <= end for start, end in benign_spans)
        for match in _DEADLY_WEAPON_TERMS.finditer(value)
    )


def policy_demotion(text: str, predicted_label: str) -> str | None:
    """Reason to suppress a top-ranked label, or None to leave it in place.

    The ensemble ranks MAJOR-25 first for narratives that merely contain a
    weapon word ("used a glue gun", "found no weapon"), which the promotion
    rules cannot correct because they only ever raise a label. Suppression is
    limited to weapon narratives whose every weapon term sits inside a benign
    context.
    """
    value = text.casefold()

    if predicted_label != "MAJOR-25":
        return None
    if not _DEADLY_WEAPON_TERMS.search(value):
        return None
    if _has_unexplained_weapon_reference(value):
        return None

    for pattern, reason in _BENIGN_WEAPON_CONTEXTS:
        if pattern.search(value):
            return reason

    return None


def policy_guardrail(text: str, predicted_label: str) -> tuple[str, str | None]:
    value = text.casefold()

    # Completed physical contact, not merely a disagreement or a threat.
    # This is an advisory injury-offense candidate; the officer verifies injury.
    if re.search(
        r"\bpunched\s+(?:another student|a student|the student|a classmate|his classmate|her classmate)"
        r"\s+in\s+the\s+(?:face|head|stomach)\b", value
    ) and not re.search(
        r"\b(not|never|no|denied|denies|allegedly|claimed|reported|threatened|"
        r"pretended|pretend|acting|play|drama|boxing|training|practice|accidentally|"
        r"if|would|could|might|without)\b|n't\b", value
    ) and predicted_label in {"NO_MATCH", "MAJOR-13", "MAJOR-42", "MINOR-06"}:
        return "MAJOR-11", "explicit_punch_to_person"

    # Deadly weapons carry the handbook's heaviest consequence, so this boundary
    # is resolved before any other. Two corpus artefacts make the raw ranking
    # unsafe here: the bare word "gun" appears in no training narrative, and
    # "bring"/"bringing" appears only in MINOR-02 narratives, so "the student is
    # bringing a gun" ranks as the University ID offense. Escalation is limited
    # to minor or unmatched rankings; a major ranking is left to the officer.
    if predicted_label == "NO_MATCH" or _MINOR_LABEL.match(predicted_label):
        if _has_unexplained_weapon_reference(value):
            return "MAJOR-25", "explicit_deadly_weapon_reference"

    # Refusing an authorised request to present the University ID is MAJOR-20.
    # The v5 model reads a plainly worded refusal as NO_MATCH; the compliant
    # side is handled later by "id_presented_on_request".
    if predicted_label == "NO_MATCH" and re.search(
        r"\b(refus\w*|declin\w*|would not|did not|said no|tumangg\w*|ayaw|hindi (?:nagpakita|ipinakita))\b"
        r".{0,80}\b(id|identification|school id|university id)\b"
        r"|\b(asked|told|hiningi|hinilingan?)\b(?:\s+\w+){0,8}\s+"
        r"\b(id|identification)\b.{0,40}\b(refus\w*|said no|tumangg\w*|ayaw)\b",
        value,
    ):
        return "MAJOR-20", "refusal_to_present_university_id"

    if predicted_label == "MAJOR-15":
        keyboard_rearrangement = re.search(
            r"\b(keyboard|keycaps?)\b.{0,90}"
            r"\b(re[- ]?arrang\w*|re[- ]?position\w*|swapp\w*|mov(?:e|ed|ing)|"
            r"ilipat|inilipat|ayos muli|inayos muli|ni-rearrange)\b"
            r"|\b(re[- ]?arrang\w*|re[- ]?position\w*|swapp\w*|mov(?:e|ed|ing)|"
            r"ilipat|inilipat|ayos muli|inayos muli|ni-rearrange)\b.{0,90}"
            r"\b(keyboard|keycaps?)\b",
            value,
        )
        damage_evidence = re.search(
            r"\b(damag|destroy|broke|broken|snapp|crack|smash|unusable|"
            r"inoperable|replacement|sinira|nasira|binali|nabali|binasag|"
            r"hindi na (?:magamit|gumana))",
            value,
        )
        damage_expressly_denied = re.search(
            r"\b(no|without)\b.{0,35}\b(damag\w*|broken|unusable|inoperable)\b"
            r"|\bwalang\b.{0,35}\b(nasira|damage)\b",
            value,
        )
        if keyboard_rearrangement and (
            not damage_evidence or damage_expressly_denied
        ):
            laboratory_context = re.search(
                r"\b(computer[- ]?lab(?:oratory)?|laboratory|lab|workstation)\b",
                value,
            )
            policy_violation = re.search(
                r"\b(without (?:permission|authorization)|unauthoriz|"
                r"contrary|prohibit|rule|policy|guideline|walang pahintulot|"
                r"labag|ipinagbabawal|bawal)",
                value,
            )
            if laboratory_context and policy_violation:
                return "MAJOR-45", "explicit_unauthorized_lab_keyboard_rearrangement"
            return "NO_MATCH", "keyboard_rearrangement_without_damage_evidence"

    if predicted_label == "NO_MATCH":
        assessment_in_progress = re.search(
            r"\b(during|while|taking|answering|ongoing|habang|nagsasagot|sumasagot)\b"
            r".{0,80}\b(exam(?:ination)?|test|quiz|midterm|pagsusulit)\b"
            r"|\b(exam(?:ination)?|test|quiz|midterm|pagsusulit)\b"
            r".{0,80}\b(while|taking|answering|ongoing|habang|nagsasagot|sumasagot)\b",
            value,
        )
        accessed_assessment_aid = re.search(
            r"\b(using|used|viewed|read|displayed|search(?:ed|ing)?|unfolded|"
            r"ginamit|tiningnan|binasa|nakabukas)\b.{0,80}"
            r"\b(phone|mobile phone|smartwatch|notes?|formula sheet|reviewer|kodigo)\b"
            r"|\b(phone|mobile phone|smartwatch|notes?|formula sheet|reviewer|kodigo)\b"
            r".{0,80}\b(displayed|showed|contained|related|answers?|questions?|"
            r"ginamit|tiningnan|binasa|nakabukas)\b",
            value,
        )
        concealed_access = re.search(
            r"\b(hidden|concealed|secretly|beneath|under|below|nakatago|palihim|kodigo)\b",
            value,
        )
        test_related_content = re.search(
            r"\b(kodigo|reviewer|formula sheet)\b"
            r"|\b(notes?|answers?)\b.{0,50}"
            r"\b(related|questions?|test|exam(?:ination)?|quiz|pagsusulit)\b",
            value,
        )
        expressly_permitted_or_finished = re.search(
            r"\b(after|matapos)\b.{0,80}\b(exam(?:ination)?|test|quiz|pagsusulit)\b"
            r".{0,80}\b(ended|finished|collected|permission|allowed|pinayagan|pahintulot)\b"
            r"|\b(allowed|permitted|authorized|pinayagan|may pahintulot)\b.{0,80}"
            r"\b(reference|notes?|phone|reviewer|formula sheet)\b",
            value,
        )

        if (
            assessment_in_progress
            and accessed_assessment_aid
            and concealed_access
            and test_related_content
            and not expressly_permitted_or_finished
        ):
            return "MAJOR-01", "explicit_concealed_assessment_aid"

    if predicted_label == "MAJOR-30":
        if re.search(r"(peaceful|mapayapa|mapayapang)", value) and re.search(
            r"(demonstration|protest|rally|forum)", value
        ) and re.search(r"(approved|aprubado|recognized|kinikilala)", value):
            return "NO_MATCH", "lawful_peaceful_demonstration"
        if re.search(r"(petition|petisyon|complaint|reklamo|grievance)", value) and re.search(
            r"(proper office|tamang opisina|recognized student council|formal dialogue|pormal na dayalogo)",
            value,
        ):
            return "NO_MATCH", "good_faith_petition_or_complaint"
        if re.search(r"(criticiz|criticis|pinuna|pumupuna)", value) and re.search(
            r"(did not encourage violence|hindi nanghikayat ng karahasan|no threats|walang pagbabanta)",
            value,
        ):
            return "NO_MATCH", "protected_policy_criticism"

    if re.search(r"(sariling|own) (?:university )?(?:portal )?account", value) and re.search(
        r"(sariling|own) (?:enrollment )?record", value
    ):
        return "NO_MATCH", "authorized_own_account_and_record"

    if re.search(r"(wore|wearing|nagsuot|suot|shirt|jacket|damit)", value) and re.search(
        r"(graphic|graphics|slogan|printed|larawan)", value
    ):
        return "MINOR-04", "inappropriate_message_or_graphic_on_clothing"

    if re.search(
        r"(denial.of.service|malware|keylogger|ransomware|firewall|endpoint protection|audit logs|hidden administrator)",
        value,
    ):
        return "MAJOR-47", "explicit_computer_security_breach"

    if re.search(
        r"(unwanted|without consent|nonconsensual|sapilitan|sapilitang)", value
    ) and re.search(
        r"(sexual touch|kiss|halik|grop|pressed against|intimate)", value
    ):
        return "MAJOR-42", "explicit_nonconsensual_lewd_contact"

    if re.search(r"(sexual orientation|gender identity)", value) and re.search(
        r"(i-out|\bout\b|expose|ibunyag)", value
    ):
        return "MAJOR-37", "outing_based_gender_harassment"

    if re.search(
        r"(intentionally damag|sadyang sinira|sinadyang sirain|destroyed|vandal)",
        value,
    ):
        return "MAJOR-15", "explicit_intentional_property_damage"

    if (
        re.search(r"(attempt|sinubukan|sinubukang)", value)
        and re.search(r"(punch|suntok)", value)
        and re.search(r"(teacher|instructor|faculty|guro)", value)
    ):
        return "MAJOR-12", "attempted_direct_assault_on_university_member"

    if re.search(r"(owned|pag-aari)", value) and re.search(
        r"(carry.*away|inalis|itinago.*bag|took|stole|kinuha)", value
    ):
        return "MAJOR-02", "explicit_misappropriation_of_owned_property"

    if re.search(r"(malaswang|lewd|obscene|indecent)", value) and re.search(
        r"(dance|sayaw)", value
    ):
        return "MAJOR-40", "explicit_public_lewd_act"

    if (
        re.search(r"(each day|araw-araw|daily|repeated)", value)
        and re.search(r"(surrender|ibigay|food|pagkain)", value)
        and re.search(r"(harm|takot|threat)", value)
    ):
        return "MAJOR-36", "repeated_coercive_bullying"

    if re.search(r"(obscene|contemptuous|mapanghamak)", value) and re.search(
        r"(security guard|teacher|instructor|faculty|guro)", value
    ):
        return "MAJOR-17", "explicit_gross_disrespect_to_university_member"

    # Compliance and benign-context demotions. The v5 corpus closed most of the
    # "charges a student for complying" gap, but a handful of narratives still
    # rank the very offense the student is complying with. Each rule fires only
    # when the model already ranked that specific offense first and the
    # narrative carries an unambiguous compliance or benign-context marker, so
    # a blanket confidence threshold - which on validation would hide ~40
    # correct low-scoring offenses to catch 3 false matches - is not used.

    if predicted_label == "MAJOR-20" and re.search(
        r"\b(present\w*|show\w*|hand\w*(?:\s+over)?|took out|pulled out|pulled .{0,20}\bout\b|"
        r"gave|let .{0,20}\bscan\b|allowed .{0,20}\bscan\b|inilabas|ipinakita|iniabot|"
        r"hinayaang i-?scan)\b.{0,80}\b(id|identification|school id|university id)\b"
        r"|\b(id|identification|school id|university id)\b.{0,80}"
        r"\b(present\w*|shown|handed|scan\w*|pulled out|inilabas|ipinakita|iniabot)\b",
        value,
    ) and re.search(
        r"\b(when asked|on request|upon request|asked (?:to (?:show|present|see)|for|by|at)|"
        r"hiningi|hinihingi|hinilingan?|nang hilingin|nang hingin)\b",
        value,
    ) and not re.search(r"\b(refus\w*|declin\w*|tumangg\w*|ayaw)\b", value):
        return "NO_MATCH", "id_presented_on_request"

    if predicted_label in {"MAJOR-26", "MINOR-04"} or _MINOR_LABEL.match(predicted_label):
        if re.search(
            r"\b(marijuana|cannabis|weed|pot|shabu|meth\w*|ecstasy|cocaine|"
            r"kush|dagga|damo|marijuana leaves|dried leaves rolled)\b",
            value,
        ) and not re.search(
            r"\b(glue|paint|flower ?pot|cooking ?pot|jack ?pot|pot luck|potluck|"
            r"no (?:drug|marijuana|weed)|walang (?:droga|marijuana))\b",
            value,
        ):
            return "MAJOR-38", "explicit_dangerous_drug_reference"

    if predicted_label in {"MAJOR-33", "MAJOR-34", "MAJOR-35"} and re.search(
        r"\b(paddl\w*|whipp\w*|beaten|beating|hazing|hazed|forced calisthenics|"
        r"binugbog|hinampas|pinaluan)\b",
        value,
    ) and re.search(
        r"\b(initiation|neophyte|applicant|admission|membership|fraternity|sorority|"
        r"inisasyon|pagsapi)\b",
        value,
    ):
        return "MAJOR-32", "explicit_hazing_during_initiation"

    if predicted_label == "MINOR-08" and re.search(
        r"\b(into|in|sa)\b\s+(?:the\s+|a\s+|ang\s+)?"
        r"(?:designated\s+|nearby\s+|itinalagang\s+)?(?:trash|waste|garbage|rubbish|basura)?\s*"
        r"\b(bin|receptacle|can|basurahan)\b",
        value,
    ) and re.search(
        r"\b(dropped|placed|threw|disposed|put|deposited|inihulog|inilagay|itinapon)\b", value
    ) and not re.search(
        r"\b(floor|ground|corridor|hallway|sahig|kalsada|litter\w*|nagkalat)\b", value
    ):
        return "NO_MATCH", "waste_placed_in_designated_bin"

    if predicted_label == "MINOR-11" and re.search(
        r"\b(borrow\w*|read|returned|sign\w*\s+out|through the (?:library )?(?:desk|counter)|"
        r"hiniram|binasa|isinauli|ibinalik)\b",
        value,
    ) and re.search(r"\b(book|libro|aklat|reference)\b", value) and not re.search(
        r"\b(hid|hidden|concealed|behind (?:a|the) shelf|itinago|nakatago|para walang ib\w*)\b",
        value,
    ):
        return "NO_MATCH", "authorized_library_use"

    if predicted_label == "MINOR-12" and re.search(
        r"\b(hair|buhok)\b", value
    ) and re.search(
        r"\b(natural|uncolou?red|undyed|not (?:dyed|colou?red)|walang (?:kulay|tina)|"
        r"hindi (?:kinulayan|tinina)|likas na kulay)\b",
        value,
    ):
        return "NO_MATCH", "natural_hair_colour"

    if predicted_label in {"MINOR-15", "MAJOR-21", "MAJOR-07"} and re.search(
        r"\b(log entry|blotter entry|blotter|logbook)\b\s*[:\-]?\s*"
        r"|\b(seen|observed|spotted|went past|walked past|passed|nakita|namataan|dumaan)\b"
        r"(?:\s+\w+){0,6}\s+\b(near|at|by|past|along|malapit sa|sa tabi ng|sa may)\b",
        value,
    ) and not re.search(
        r"\b(carr\w*|possess\w*|holding|hawak|dala|entered|pumasok|used|ginamit|"
        r"restricted|bawal|no entry|prohibited|without (?:permission|authoriz)|walang pahintulot)\b",
        value,
    ):
        return "NO_MATCH", "insufficient_detail_in_log_entry"

    if predicted_label in {"MAJOR-10", "MAJOR-11"} and re.search(
        r"\b(bump\w*|collid\w*|brush\w*|knock\w*\s+into|nagkabanggaan|nabangga|nadikit)\b",
        value,
    ) and re.search(
        r"\b(accident\w*|apolog\w*|said sorry|nag-?sorry|humingi ng paumanhin|"
        r"hindi sinasadya|di-?sinasadya)\b",
        value,
    ) and not re.search(
        r"\b(punch\w*|hit|struck|fight|fought|brawl|away\w*|kick\w*|suntok|away|"
        r"injur\w*|sugat|nasaktan)\b",
        value,
    ):
        return "NO_MATCH", "accidental_incidental_contact"

    return predicted_label, None
