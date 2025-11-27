$(document).ready(function() {
    $('.menu-toggle').on('click', function() {
        $('.navbar-menu').toggleClass('active');
    });

    window.showNotification = function(message, type = 'success') {
        const notification = $(`
            <div class="alert alert-${type}" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `);
        $('body').append(notification);
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    };

    window.openModal = function(modalId) {
        $(`#${modalId}`).addClass('active');
        $('body').css('overflow', 'hidden');
    };

    window.closeModal = function(modalId) {
        $(`#${modalId}`).removeClass('active');
        $('body').css('overflow', 'auto');
    };

    $('.modal-overlay').on('click', function(e) {
        if ($(e.target).hasClass('modal-overlay')) {
            $(this).removeClass('active');
            $('body').css('overflow', 'auto');
        }
    });

    $('.modal-close').on('click', function() {
        $(this).closest('.modal-overlay').removeClass('active');
        $('body').css('overflow', 'auto');
    });

    $('.tabs .tab').on('click', function() {
        const target = $(this).data('target');
        
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        
        $(this).closest('.card').find('.tab-content').removeClass('active');
        $(this).closest('.card').find(`#${target}`).addClass('active');
    });

    $('.file-upload').on('click', function() {
        $(this).find('input[type="file"]').click();
    });

    $('.file-upload input[type="file"]').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).siblings('p').text(fileName);
        }
    });

    window.formatDate = function(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    };

    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    window.apiRequest = function(url, method = 'GET', data = null) {
        return $.ajax({
            url: url,
            method: method,
            data: data ? JSON.stringify(data) : null,
            contentType: 'application/json',
            dataType: 'json'
        });
    };

    $('form.ajax-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const url = form.attr('action');
        const method = form.attr('method') || 'POST';
        const formData = new FormData(this);
        
        $.ajax({
            url: url,
            method: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    }
                } else {
                    showNotification(response.message || 'An error occurred', 'danger');
                }
            },
            error: function() {
                showNotification('An error occurred. Please try again.', 'danger');
            }
        });
    });

    window.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    $('input[data-search]').on('input', debounce(function() {
        const searchTerm = $(this).val().toLowerCase();
        const target = $(this).data('search');
        
        $(target).each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(searchTerm));
        });
    }, 300));
});
