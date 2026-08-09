document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('speakerProfileModal');
    
    if (!modalElement) {
        return;
    }

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

        element.classList.toggle('hidden', !isVisible);
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
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-primary-500/10 text-primary-400 px-4 py-2 text-sm font-medium hover:bg-primary-500/20 transition-colors">' +
            '<i class="' + escapeHtml(iconClass) + '"></i>' + escapeHtml(label) + '</a>';
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
                return '<span class="inline-flex items-center rounded-full bg-primary-500/10 text-primary-300 px-3 py-1 text-sm border border-primary-500/20">' + escapeHtml(item) + '</span>';
            }).join('');
        }
        expertise.innerHTML = expertiseMarkup;
        setVisible(expertise.parentElement, !!expertiseMarkup);

        biography.innerHTML = data.biography || '<p class="text-gray-400 mb-0">Biography coming soon.</p>';

        // Show modal
        modalElement.classList.remove('opacity-0', 'pointer-events-none');
        modalElement.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }

    function closeSpeakerModal() {
        modalElement.classList.remove('opacity-100');
        modalElement.classList.add('opacity-0', 'pointer-events-none');
        document.body.style.overflow = '';
    }

    // Close on backdrop click
    modalElement.addEventListener('click', function(event) {
        if (event.target === modalElement) {
            closeSpeakerModal();
        }
    });

    // Close on escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modalElement.classList.contains('pointer-events-none')) {
            closeSpeakerModal();
        }
    });

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
