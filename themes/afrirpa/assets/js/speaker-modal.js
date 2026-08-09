document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('speakerProfileModal');

    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    var modalInstance = new bootstrap.Modal(modalElement);
    var image = modalElement.querySelector('[data-speaker-modal-image]');
    var featured = modalElement.querySelector('[data-speaker-modal-featured]');
    var name = modalElement.querySelector('[data-speaker-modal-name]');
    var role = modalElement.querySelector('[data-speaker-modal-role]');
    var country = modalElement.querySelector('[data-speaker-modal-country]');
    var socials = modalElement.querySelector('[data-speaker-modal-socials]');
    var expertise = modalElement.querySelector('[data-speaker-modal-expertise]');
    var biography = modalElement.querySelector('[data-speaker-modal-biography]');

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char];
        });
    }

    function setVisible(element, isVisible) {
        if (!element) {
            return;
        }

        element.classList.toggle('d-none', !isVisible);
    }

    function buildRole(data) {
        var parts = [];

        if (data.title) {
            parts.push(data.title);
        }

        if (data.company) {
            parts.push(data.company);
        }

        return parts.join(', ');
    }

    function buildSocialLink(label, url, iconClass) {
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="btn-main btn-line btn-sm">' +
            '<span><i class="' + escapeHtml(iconClass) + ' me-2"></i>' + escapeHtml(label) + '</span></a>';
    }

    function openSpeakerModal(trigger) {
        if (!trigger) {
            return;
        }

        var rawData = trigger.getAttribute('data-speaker-popup');
        if (!rawData) {
            return;
        }

        var data;
        try {
            data = JSON.parse(rawData);
        }
        catch (error) {
            return;
        }

        image.src = data.image_url || '';
        image.alt = data.full_name || 'Speaker';

        name.textContent = data.full_name || 'Speaker';

        var roleText = buildRole(data);
        role.textContent = roleText || 'Conference Speaker';

        country.textContent = data.country || '';
        setVisible(country, !!data.country);

        setVisible(featured, !!data.is_featured);

        var socialMarkup = '';
        if (data.email) {
            socialMarkup += buildSocialLink('Email', 'mailto:' + data.email, 'fa fa-envelope');
        }
        if (data.linkedin_url) {
            socialMarkup += buildSocialLink('LinkedIn', data.linkedin_url, 'fa-brands fa-linkedin-in');
        }
        if (data.website_url) {
            socialMarkup += buildSocialLink('Website', data.website_url, 'fa fa-globe');
        }
        socials.innerHTML = socialMarkup;
        setVisible(socials, !!socialMarkup);

        var expertiseMarkup = '';
        if (Array.isArray(data.expertise)) {
            expertiseMarkup = data.expertise.map(function (item) {
                return '<span class="badge badge-light">' + escapeHtml(item) + '</span>';
            }).join('');
        }
        expertise.innerHTML = expertiseMarkup;
        setVisible(expertise.parentElement, !!expertiseMarkup);

        biography.innerHTML = data.biography || '<p class="mb-0">Biography coming soon.</p>';

        modalInstance.show();
    }

    document.addEventListener('click', function (event) {
        openSpeakerModal(event.target.closest('[data-speaker-popup-trigger]'));
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        var trigger = event.target.closest('[data-speaker-popup-trigger][role="button"]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        openSpeakerModal(trigger);
    });
});
