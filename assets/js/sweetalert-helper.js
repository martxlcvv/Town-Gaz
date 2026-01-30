// SweetAlert Helper Functions

// Success Alert
function showSuccess(title = 'Success!', message = '', timer = 2000) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonColor: '#27ae60',
        timer: timer,
        timerProgressBar: true,
        showConfirmButton: timer ? false : true
    });
}

// Error Alert
function showError(title = 'Error!', message = '') {
    Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonColor: '#e74c3c'
    });
}

// Warning Alert
function showWarning(title = 'Warning!', message = '') {
    Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        confirmButtonColor: '#f39c12'
    });
}

// Info Alert
function showInfo(title = 'Info', message = '') {
    Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        confirmButtonColor: '#3498db'
    });
}

// Confirmation Dialog
function showConfirm(title = 'Are you sure?', message = '', confirmText = 'Yes', cancelText = 'Cancel', confirmCallback = null) {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#27ae60',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    }).then((result) => {
        if (result.isConfirmed && confirmCallback) {
            confirmCallback();
        }
        return result.isConfirmed;
    });
}

// Delete Confirmation
function showDeleteConfirm(itemName = 'this item', confirmCallback = null) {
    return Swal.fire({
        title: 'Delete ' + itemName + '?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-2"></i>Delete',
        cancelButtonText: '<i class="bi bi-x-circle me-2"></i>Cancel'
    }).then((result) => {
        if (result.isConfirmed && confirmCallback) {
            confirmCallback();
        }
        return result.isConfirmed;
    });
}

// Loading Alert
function showLoading(title = 'Please wait...', message = '') {
    Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// Close Loading Alert
function closeLoading() {
    Swal.close();
}

// Form Submit with Confirmation
function confirmFormSubmit(event, title = 'Confirm Submit', message = 'Are you sure you want to proceed?') {
    event.preventDefault();
    const form = event.target;
    
    Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#065275',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}

// Toast Notification (top-right corner)
function showToast(title = 'Notification', icon = 'info', timer = 3000) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: timer,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: icon,
        title: title
    });
}

// Custom HTML Alert
function showCustom(html, title = '', confirmText = 'OK', confirmColor = '#065275') {
    Swal.fire({
        title: title,
        html: html,
        confirmButtonColor: confirmColor,
        confirmButtonText: confirmText
    });
}
