const form = document.querySelector('.signup-form');

if (form) {
    const password = form.elements.password;
    const confirmPassword = form.elements.confirm_password;
    const fullName = form.elements.full_name;
    const mobile = form.elements.mobile;
    const strength = document.querySelector('#password-strength');
    const strengthText = document.querySelector('#password-strength-text');

    function validateStudentDetails() {
        fullName.setCustomValidity(
            fullName.value !== '' && !/^[\p{L}\p{M} .'-]{1,80}$/u.test(fullName.value.trim())
                ? 'Use letters, spaces, apostrophes, periods, or hyphens in your name.'
                : ''
        );
        mobile.setCustomValidity(
            mobile.value !== '' && !/^\+?[0-9][0-9\s().-]{6,19}$/.test(mobile.value.trim())
                ? 'Enter a valid mobile number.'
                : ''
        );
    }

    function validatePasswords() {
        const byteLength = new TextEncoder().encode(password.value).length;
        const hasControlCharacter = /[\u0000-\u001F\u007F]/.test(password.value);
        const strengthScore = password.value === ''
            ? 0
            : [
                byteLength >= 8,
                byteLength >= 12,
                /[A-Z]/.test(password.value) && /[a-z]/.test(password.value),
                /[0-9]/.test(password.value) && /[^A-Za-z0-9]/.test(password.value)
            ].filter(Boolean).length;
        const strengthLabels = ['Enter a password to check its strength.', 'Weak password.', 'Fair password.', 'Good password.', 'Strong password.'];

        password.setCustomValidity(
            password.value !== '' && (byteLength < 8 || byteLength > 72 || hasControlCharacter)
                ? 'Password must be 8–72 UTF-8 bytes and cannot contain control characters.'
                : ''
        );
        confirmPassword.setCustomValidity(
            confirmPassword.value !== '' && password.value !== confirmPassword.value
                ? 'The password and confirmation do not match.'
                : ''
        );
        strength.value = String(strengthScore);
        strengthText.textContent = strengthLabels[strengthScore];
    }

    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    fullName.addEventListener('input', validateStudentDetails);
    mobile.addEventListener('input', validateStudentDetails);
    form.addEventListener('submit', () => {
        validateStudentDetails();
        validatePasswords();
    });
}
