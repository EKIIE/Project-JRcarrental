function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function checkPasswordMatch() {
    const pw = document.getElementById("password").value;
    const confirm = document.getElementById("confirm_password").value;
    const status = document.getElementById("match-status");

    if (confirm === "") {
        status.innerHTML = "";
        return;
    }

    if (pw === confirm) {
        status.innerHTML = '<i class="fa-solid fa-check" style="color:green; margin-left:8px;"></i>';
    } else {
        status.innerHTML = '<i class="fa-solid fa-xmark" style="color:red; margin-left:8px;"></i>';
    }
}
document.addEventListener('DOMContentLoaded', () => {
  const pw = document.getElementById('password');
  const cpw = document.getElementById('confirm_password');
  const status = document.getElementById('match-status');
  const submitBtn = document.getElementById('submitBtn');

  function check() {
    if (!cpw.value) {
      status.innerHTML = '';
      submitBtn.disabled = true;
      return;
    }
    const ok = pw.value === cpw.value;
    status.innerHTML = ok
      ? '<i class="fa-solid fa-check" style="color:green;"></i>'
      : '<i class="fa-solid fa-xmark" style="color:red;"></i>';
    submitBtn.disabled = !ok;
  }

  pw.addEventListener('input', check);
  cpw.addEventListener('input', check);

  // ฟังก์ชันตา
  window.togglePassword = function(fieldId, icon) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
      field.type = 'text';
      icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
    } else {
      field.type = 'password';
      icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
    }
  };

  check(); // เรียกครั้งแรก
});
