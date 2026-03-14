// ==============================
// TOGGLE PASSWORD VISIBILITY
// ==============================
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ==============================
// PASSWORD STRENGTH CHECKER
// ==============================
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    return strength;
}

function updatePasswordStrength(password) {
    const strengthBar = document.getElementById('strengthBar');
    if (!strengthBar) return;
    
    const strength = checkPasswordStrength(password);
    
    strengthBar.className = 'strength-bar';
    
    if (password.length === 0) {
        strengthBar.style.width = '0';
    } else if (strength <= 2) {
        strengthBar.classList.add('weak');
    } else if (strength <= 3) {
        strengthBar.classList.add('medium');
    } else if (strength <= 4) {
        strengthBar.classList.add('strong');
    } else {
        strengthBar.classList.add('very-strong');
    }
}

// ==============================
// PASSWORD REQUIREMENTS CHECK
// ==============================
function checkPasswordRequirements() {
    const password = document.getElementById('newPassword')?.value || '';
    const confirm = document.getElementById('confirmNewPassword')?.value || '';
    
    const reqLength = document.getElementById('req-length');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const reqMatch = document.getElementById('req-match');
    
    if (reqLength) {
        if (password.length >= 8) {
            reqLength.classList.add('valid');
            reqLength.innerHTML = '<i class="fa-regular fa-circle-check"></i> At least 8 characters';
        } else {
            reqLength.classList.remove('valid');
            reqLength.innerHTML = '<i class="fa-regular fa-circle-xmark"></i> At least 8 characters';
        }
    }
    
    if (reqNumber) {
        if (password.match(/[0-9]+/)) {
            reqNumber.classList.add('valid');
            reqNumber.innerHTML = '<i class="fa-regular fa-circle-check"></i> At least 1 number';
        } else {
            reqNumber.classList.remove('valid');
            reqNumber.innerHTML = '<i class="fa-regular fa-circle-xmark"></i> At least 1 number';
        }
    }
    
    if (reqSpecial) {
        if (password.match(/[$@#&!]+/)) {
            reqSpecial.classList.add('valid');
            reqSpecial.innerHTML = '<i class="fa-regular fa-circle-check"></i> At least 1 special character';
        } else {
            reqSpecial.classList.remove('valid');
            reqSpecial.innerHTML = '<i class="fa-regular fa-circle-xmark"></i> At least 1 special character';
        }
    }
    
    if (reqMatch) {
        if (password && confirm && password === confirm) {
            reqMatch.classList.add('valid');
            reqMatch.innerHTML = '<i class="fa-regular fa-circle-check"></i> Passwords match';
        } else {
            reqMatch.classList.remove('valid');
            reqMatch.innerHTML = '<i class="fa-regular fa-circle-xmark"></i> Passwords match';
        }
    }
}

// ==============================
// CODE INPUT NAVIGATION
// ==============================
function moveToNext(current, nextIndex) {
    if (current.value.length >= current.maxLength) {
        const next = document.querySelectorAll('.code-digit')[nextIndex];
        if (next) {
            next.focus();
        }
    }
    
    // Auto submit when all fields are filled
    const inputs = document.querySelectorAll('.code-digit');
    let allFilled = true;
    inputs.forEach(input => {
        if (!input.value) allFilled = false;
    });
    
    if (allFilled) {
        verifyCode();
    }
}

// ==============================
// RESET PASSWORD FLOW
// ==============================
function sendResetCode() {
    const email = document.getElementById('resetEmail').value;
    
    if (!email) {
        showNotification('Please enter your email', 'error');
        return;
    }
    
    if (!isValidEmail(email)) {
        showNotification('Please enter a valid email', 'error');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    btn.classList.add('loading');
    
    // Simulate API call
    setTimeout(() => {
        btn.classList.remove('loading');
        
        // Hide step 1, show step 2
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'flex';
        
        showNotification('Reset code sent to your email', 'success');
        startTimer(120); // 2 minute timer
    }, 1500);
}

function verifyCode() {
    const inputs = document.querySelectorAll('.code-digit');
    let code = '';
    inputs.forEach(input => {
        code += input.value;
    });
    
    if (code.length < 6) {
        showNotification('Please enter the complete 6-digit code', 'error');
        return;
    }
    
    // Show loading state
    const btn = document.querySelector('#step2 .auth-btn');
    btn.classList.add('loading');
    
    // Simulate verification
    setTimeout(() => {
        btn.classList.remove('loading');
        
        // Hide step 2, show step 3
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'flex';
        
        showNotification('Code verified successfully', 'success');
    }, 1500);
}

function updatePassword() {
    const password = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmNewPassword').value;
    
    if (!password || !confirm) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (password.length < 8) {
        showNotification('Password must be at least 8 characters', 'error');
        return;
    }
    
    if (password !== confirm) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    // Show loading state
    const btn = document.querySelector('#step3 .auth-btn');
    btn.classList.add('loading');
    
    // Simulate password update
    setTimeout(() => {
        btn.classList.remove('loading');
        
        // Show success message
        showSuccessScreen();
    }, 1500);
}

// ==============================
// TIMER FUNCTION
// ==============================
function startTimer(duration) {
    let timer = duration;
    const timerElement = document.getElementById('timer');
    const resendBtn = document.querySelector('.resend-btn');
    
    resendBtn.disabled = true;
    
    const interval = setInterval(() => {
        const minutes = Math.floor(timer / 60);
        let seconds = timer % 60;
        
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        timerElement.textContent = `${minutes}:${seconds}`;
        
        if (--timer < 0) {
            clearInterval(interval);
            timerElement.textContent = '00:00';
            resendBtn.disabled = false;
        }
    }, 1000);
}

function resendCode() {
    const resendBtn = document.querySelector('.resend-btn');
    resendBtn.disabled = true;
    
    showNotification('New code sent!', 'success');
    startTimer(120); // Reset timer
}

// ==============================
// SUCCESS SCREEN
// ==============================
function showSuccessScreen() {
    const authCard = document.querySelector('.auth-card');
    const originalContent = authCard.innerHTML;
    
    authCard.innerHTML = `
        <div class="success-check">
            <i class="fa-regular fa-circle-check"></i>
        </div>
        <h2 style="text-align: center; margin-bottom: 10px;">Password Updated!</h2>
        <p style="text-align: center; color: var(--gray); margin-bottom: 30px;">
            Your password has been successfully updated.
        </p>
        <a href="login.html" class="auth-btn btn-primary" style="text-align: center; text-decoration: none;">
            <i class="fa-regular fa-arrow-right-to-bracket"></i>
            Go to Login
        </a>
    `;
}

// ==============================
// NOTIFICATION SYSTEM
// ==============================
function showNotification(message, type) {
    // Remove existing notification
    const existing = document.querySelector('.auth-notification');
    if (existing) {
        existing.remove();
    }
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `auth-notification ${type}`;
    notification.innerHTML = `
        <i class="fa-regular fa-circle-${type === 'success' ? 'check' : 'xmark'}"></i>
        <span>${message}</span>
    `;
    
    // Style notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
        color: white;
        border-radius: 50px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// ==============================
// EMAIL VALIDATION
// ==============================
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ==============================
// FORM HANDLERS
// ==============================
document.addEventListener('DOMContentLoaded', function() {
    // Login form handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember')?.checked || false;
            
            // Simple validation
            if (!email || !password) {
                showNotification('Please fill in all fields', 'error');
                return;
            }
            
            // Show loading
            const btn = this.querySelector('button[type="submit"]');
            btn.classList.add('loading');
            
            // Simulate login
            setTimeout(() => {
                btn.classList.remove('loading');
                
                // Demo credentials check
                if (email === 'admin@inventra.com' && password === 'password123') {
                    showNotification('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1500);
                } else {
                    showNotification('Invalid email or password', 'error');
                }
            }, 1500);
        });
    }
    
    // Signup form handler
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const email = document.getElementById('signupEmail').value;
            const password = document.getElementById('signupPassword').value;
            const confirm = document.getElementById('confirmPassword').value;
            const terms = document.getElementById('terms').checked;
            
            // Validation
            if (!firstName || !lastName || !email || !password || !confirm) {
                showNotification('Please fill in all fields', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showNotification('Please enter a valid email', 'error');
                return;
            }
            
            if (password.length < 8) {
                showNotification('Password must be at least 8 characters', 'error');
                return;
            }
            
            if (password !== confirm) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            if (!terms) {
                showNotification('Please accept the terms and conditions', 'error');
                return;
            }
            
            // Show loading
            const btn = this.querySelector('button[type="submit"]');
            btn.classList.add('loading');
            
            // Simulate signup
            setTimeout(() => {
                btn.classList.remove('loading');
                showNotification('Account created successfully!', 'success');
                
                // Redirect to login after 2 seconds
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            }, 1500);
        });
    }
    
    // Password strength checker
    const passwordInput = document.getElementById('signupPassword');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    // Password requirements checker (reset page)
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmNewPassword');
    
    if (newPassword) {
        newPassword.addEventListener('input', checkPasswordRequirements);
        confirmPassword.addEventListener('input', checkPasswordRequirements);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});