const form = document.querySelector('.signup-form');

if (form) {
    const password = form.elements.password;
    const confirmPassword = form.elements.confirm_password;

    function validatePasswords() {
        const byteLength = new TextEncoder().encode(password.value).length;
        const hasControlCharacter = /[\u0000-\u001F\u007F]/.test(password.value);

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
    }

    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    form.addEventListener('submit', validatePasswords);
}
