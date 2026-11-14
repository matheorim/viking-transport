const inputTelephone   = document.getElementById('telephone');
const inputEmail       = document.getElementById('email');
const inputPassword    = document.getElementById('password');
const inputConfirm     = document.getElementById('confirm-password');
const btnSubmit        = document.querySelector('.submit');

const errTelephone = document.getElementById('err-telephone');
const errEmail     = document.getElementById('err-email');
const errPassword  = document.getElementById('err-password');
const errConfirm   = document.getElementById('err-confirm');

// Regex telephone : valide le format final après formatage
const regexTelephone = /^(\+33\s6|\+33\s7|06|07)(\s\d{2}){4}$/;

// Regex email standard
const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function formatTelephone(val) {
    // On garde uniquement les chiffres et le +
    let digits = val.replace(/[^\d+]/g, '');

    // Cas +33 : +33 6 XX XX XX XX
    if (digits.startsWith('+33')) {
        digits = digits.slice(3).replace(/^0/, '');
        let clean = digits.replace(/\D/g, '').slice(0, 9);
        let parts = [];
        if (clean.length > 0) { parts.push(clean.slice(0, 1)); }
        for (let i = 1; i < clean.length; i += 2) {
            parts.push(clean.slice(i, i + 2));
        }
        return '+33 ' + parts.join(' ');
    }

    // Cas 0X : 06 XX XX XX XX
    let clean = digits.replace(/\D/g, '').slice(0, 10);
    let parts = [];
    for (let i = 0; i < clean.length; i += 2) {
        parts.push(clean.slice(i, i + 2));
    }
    return parts.join(' ');
}

function setFieldState(input, errorEl, isValid) {
    if (isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorEl.classList.remove('visible');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        errorEl.classList.add('visible');
    }
}

function validateTelephone() {
    const val = inputTelephone.value.trim();
    if (val === '') {
        inputTelephone.classList.remove('is-valid', 'is-invalid');
        errTelephone.classList.remove('visible');
        return false;
    }
    const isValid = regexTelephone.test(val);
    setFieldState(inputTelephone, errTelephone, isValid);
    return isValid;
}

function validateEmail() {
    const val = inputEmail.value.trim();
    if (val === '') {
        inputEmail.classList.remove('is-valid', 'is-invalid');
        errEmail.classList.remove('visible');
        return false;
    }
    const isValid = regexEmail.test(val);
    setFieldState(inputEmail, errEmail, isValid);
    return isValid;
}

function validatePassword() {
    const val = inputPassword.value;
    if (val === '') {
        inputPassword.classList.remove('is-valid', 'is-invalid');
        errPassword.classList.remove('visible');
        return false;
    }
    const isValid = val.length >= 8;
    setFieldState(inputPassword, errPassword, isValid);
    return isValid;
}

function validateConfirm() {
    const val = inputConfirm.value;
    if (val === '') {
        inputConfirm.classList.remove('is-valid', 'is-invalid');
        errConfirm.classList.remove('visible');
        return false;
    }
    const isValid = val === inputPassword.value && val !== '';
    setFieldState(inputConfirm, errConfirm, isValid);
    return isValid;
}

function updateSubmitState() {
    const allValid =
        regexTelephone.test(inputTelephone.value.trim()) &&
        regexEmail.test(inputEmail.value.trim()) &&
        inputPassword.value.length >= 8 &&
        inputConfirm.value === inputPassword.value &&
        inputConfirm.value !== '';

    if (allValid) {
        btnSubmit.disabled = false;
    } else {
        btnSubmit.disabled = true;
    }
}

inputTelephone.addEventListener('input', function() {
    const cursorPos = inputTelephone.selectionStart;
    const oldVal    = inputTelephone.value;
    const newVal    = formatTelephone(oldVal);
    inputTelephone.value = newVal;
    // Ajuste le curseur si on n'est pas en fin de chaîne
    const diff = newVal.length - oldVal.length;
    inputTelephone.setSelectionRange(cursorPos + diff, cursorPos + diff);
    if (inputTelephone.classList.contains('is-invalid') || inputTelephone.classList.contains('is-valid')) {
        validateTelephone();
    }
    updateSubmitState();
});
inputTelephone.addEventListener('blur', function() { validateTelephone(); updateSubmitState(); });

inputEmail.addEventListener('blur',  function() { validateEmail(); updateSubmitState(); });
inputEmail.addEventListener('input', function() { if (inputEmail.classList.contains('is-invalid')) { validateEmail(); } updateSubmitState(); });

inputPassword.addEventListener('blur',  function() { validatePassword(); validateConfirm(); updateSubmitState(); });
inputPassword.addEventListener('input', function() { if (inputPassword.classList.contains('is-invalid')) { validatePassword(); } validateConfirm(); updateSubmitState(); });

inputConfirm.addEventListener('blur',  function() { validateConfirm(); updateSubmitState(); });
inputConfirm.addEventListener('input', function() { validateConfirm(); updateSubmitState(); });

btnSubmit.disabled = true;

// SVG œil ouvert
const svgEyeOpen = `<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>`;
// SVG œil barré
const svgEyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><path d="M10.73 10.73a3 3 0 004.24 4.24" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path><line x1="1" y1="1" x2="23" y2="23" stroke-width="2" stroke-linecap="round"></line>`;

function toggleVisibility(inputEl, svgEl) {
    if (inputEl.type === 'password') {
        inputEl.type = 'text';
        svgEl.innerHTML = svgEyeClosed;
    } else {
        inputEl.type = 'password';
        svgEl.innerHTML = svgEyeOpen;
    }
}

document.getElementById('toggle-password').addEventListener('click', function() {
    toggleVisibility(inputPassword, document.getElementById('eye-password'));
});

document.getElementById('toggle-confirm').addEventListener('click', function() {
    toggleVisibility(inputConfirm, document.getElementById('eye-confirm'));
});

btnSubmit.addEventListener('click', function(evt) {
    evt.preventDefault();
    const form = btnSubmit.closest('form');
    const ok = validateTelephone() && validateEmail() && validatePassword() && validateConfirm();
    if (ok) {
        form.submit();
    }
});