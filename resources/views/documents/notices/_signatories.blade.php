{{--
    The Prepared by / Noted by / Received copy block that closes F-002 and F-003.

    Each signatory's label lives in the same cell as their own name and title - not in a
    separate row above both names - so a page break can never land between a "Prepared by:"
    / "Noted by:" label and the name it belongs to. A two-row layout (all labels, then all
    names) let exactly that happen when the table fell near a page boundary.
--}}
<table class="sig">
    <tr>
        <td>
            <div class="sig-role-label">Prepared by:</div>
            <div class="sig-name">{{ $preparedBy?->full_name ?? '________________________' }}</div>
            <div class="sig-title">{{ $preparedByTitle }}</div>
        </td>
        <td>
            <div class="sig-role-label">Noted by:</div>
            <div class="sig-name">{{ $notedBy?->full_name ?? '________________________' }}</div>
            <div class="sig-title">Student Discipline Coordinator</div>
        </td>
    </tr>
</table>

<table class="ack">
    <tr>
        <td>
            <div class="ack-label">Received copy:</div>
            <div class="ack-line"></div>
            <div class="ack-caption">Student&rsquo;s Signature over Printed Name</div>
            <div class="ack-field">Date: <span class="ack-field-line"></span></div>
            <div class="ack-field">Time: <span class="ack-field-line"></span></div>
            <div class="ack-field">Contact No.: <span class="ack-field-line"></span></div>
        </td>
        <td>
            <div class="ack-label">Conformed by:</div>
            <div class="ack-line"></div>
            <div class="ack-caption">Parents/Guardians Signature over Printed Name</div>
        </td>
    </tr>
</table>
