<label for="employee-name">Full name</label>
<input id="employee-name" name="name" value="{{ old('name', $profile->name ?? '') }}" maxlength="255" required>
<label for="employee-code">Employee code</label>
<input id="employee-code" name="code" value="{{ old('code', $profile->code ?? '') }}" maxlength="40" required>
<label for="employee-department">Department (optional)</label>
<input id="employee-department" name="department" value="{{ old('department', $profile->department ?? '') }}" maxlength="255">
