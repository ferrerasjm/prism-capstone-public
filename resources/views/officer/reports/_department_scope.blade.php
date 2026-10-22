{{-- Assigned-departments pill for the Reports index and Risk Dashboard section
     headers. Renders nothing unless the controller is department-scoped
     (discipline officer); the coordinator controller passes $assignedDepartments
     as null.

     tone: 'dark' (default) — white chips for a blue #3156C7 section-head banner,
           matching the Cases and Sanctions queues.
           'light' — indigo chips for a light section-head banner. --}}
@php($__hasDepartments = ! is_null($assignedDepartments ?? null) && $assignedDepartments->isNotEmpty())
@if($__hasDepartments)
    <x-department-scope
        :departments="$assignedDepartments"
        label="Departments"
        class="report-department-scope {{ ($tone ?? 'dark') === 'light' ? 'prism-dept-scope--light' : 'report-department-scope--onblue' }}" />
@endif
