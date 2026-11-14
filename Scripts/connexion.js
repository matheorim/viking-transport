const togglePassword = document.getElementById('toggle-password');
const passwordInput = document.getElementById('password');
const eyeIcon = document.getElementById('eye-icon');

const svgEyeOpen = `<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>`;
const svgEyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M10.73 10.73a3 3 0 004.24 4.24" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><line x1="1" y1="1" x2="23" y2="23" stroke-width="2" stroke-linecap="round"></line>`;

togglePassword.addEventListener('click', function () {
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = svgEyeClosed;
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = svgEyeOpen;
    }
});