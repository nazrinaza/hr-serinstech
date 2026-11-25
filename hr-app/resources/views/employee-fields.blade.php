<label for="employee-name">Full name</label>
<input id="employee-name" name="name" value="{{ old('name', $profile->name ?? '') }}" maxlength="255" required>
<label for="employee-code">Employee code</label>
<input id="employee-code" name="code" value="{{ old('code', $profile->code ?? '') }}" maxlength="40" required>
<label for="employee-department">Department (optional)</label>
@php
    $departments = ['Finance', 'Staff', 'Account', 'Human Resources', 'General Worker', 'Administration', 'Operations', 'Sales', 'Support', 'IT'];
    $currentDepartment = $profile->department ?? '';
    if ($currentDepartment !== '' && !in_array($currentDepartment, $departments, true)) {
        $departments[] = $currentDepartment;
    }
    $selectedDepartment = old('department', $currentDepartment);
@endphp
<select id="employee-department" name="department">
    <option value="">Select department</option>
    @foreach($departments as $department)
        <option value="{{ $department }}" @selected($selectedDepartment === $department)>{{ $department }}</option>
    @endforeach
</select>
