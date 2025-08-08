// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/storefront/src/plugin/campaign-widget.plugin.js
import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class CampaignWidgetPlugin extends Plugin {
    static options = {
        campaignId: null,
        updateInterval: 60000, // Update every minute
        countdownSelector: '.campaign-countdown',
        progressBarSelector: '.campaign-progress',
        pledgeFormSelector: '.campaign-pledge-form',
        statusUrl: '/campaign/{id}/status'
    };

    init() {
        this._client = new HttpClient();
        this.campaignId = this.options.campaignId || this.el.dataset.campaignId;

        if (!this.campaignId) {
            console.error('Campaign ID is required for campaign widget');
            return;
        }

        this._initializeCountdown();
        this._initializePledgeForm();
        this._startAutoUpdate();
        this._initializeShareButtons();
    }

    /**
     * Initialize countdown timer
     */
    _initializeCountdown() {
        const countdownElement = this.el.querySelector(this.options.countdownSelector);
        if (!countdownElement) return;

        const endDate = new Date(countdownElement.dataset.endDate);

        this.countdownInterval = setInterval(() => {
            const now = new Date();
            const distance = endDate - now;

            if (distance < 0) {
                clearInterval(this.countdownInterval);
                this._showCampaignEnded();
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            this._updateCountdownDisplay(days, hours, minutes, seconds);

            // Add urgency styling in last 24 hours
            if (days === 0) {
                countdownElement.classList.add('countdown-urgent');
            }
        }, 1000);
    }

    /**
     * Update countdown display
     */
    _updateCountdownDisplay(days, hours, minutes, seconds) {
        const countdownElement = this.el.querySelector(this.options.countdownSelector);
        if (!countdownElement) return;

        const daysEl = countdownElement.querySelector('[data-days]');
        const hoursEl = countdownElement.querySelector('[data-hours]');
        const minutesEl = countdownElement.querySelector('[data-minutes]');
        const secondsEl = countdownElement.querySelector('[data-seconds]');

        if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
        if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
        if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
        if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, '0');
    }

    /**
     * Show campaign ended message
     */
    _showCampaignEnded() {
        const countdownElement = this.el.querySelector(this.options.countdownSelector);
        if (countdownElement) {
            countdownElement.innerHTML = `
                <div class="alert alert-warning">
                    <i class="icon icon-clock"></i>
                    Campaign has ended
                </div>
            `;
        }

        // Disable pledge form
        const pledgeForm = this.el.querySelector(this.options.pledgeFormSelector);
        if (pledgeForm) {
            pledgeForm.querySelectorAll('input, button').forEach(el => {
                el.disabled = true;
            });
        }
    }

    /**
     * Initialize pledge form
     */
    _initializePledgeForm() {
        const form = this.el.querySelector(this.options.pledgeFormSelector);
        if (!form) return;

        form.addEventListener('submit', this._onPledgeSubmit.bind(this));

        // Quantity change handler
        const quantityInput = form.querySelector('input[name="quantity"]');
        if (quantityInput) {
            quantityInput.addEventListener('change', this._onQuantityChange.bind(this));
        }
    }

    /**
     * Handle pledge form submission
     */
    _onPledgeSubmit(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');

        // Disable submit button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        }

        this._client.post(form.action, formData, this._onPledgeResponse.bind(this));
    }

    /**
     * Handle pledge response
     */
    _onPledgeResponse(response) {
        const data = JSON.parse(response);
        const form = this.el.querySelector(this.options.pledgeFormSelector);
        const submitButton = form.querySelector('button[type="submit"]');

        // Re-enable submit button
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Pledge Now';
        }

        if (data.success) {
            this._showSuccessMessage(data.message || 'Successfully pledged to campaign!');
            this._updateCampaignStatus();

            // Redirect to cart after short delay
            setTimeout(() => {
                window.location.href = '/checkout/cart';
            }, 2000);
        } else {
            this._showErrorMessage(data.message || 'An error occurred. Please try again.');
        }
    }

    /**
     * Handle quantity change
     */
    _onQuantityChange(event) {
        const quantity = parseInt(event.target.value);
        const depositInfo = this.el.querySelector('.deposit-amount');

        if (depositInfo) {
            // Calculate and display deposit amount
            const unitDeposit = parseFloat(depositInfo.dataset.unitDeposit || 0);
            const totalDeposit = (unitDeposit * quantity).toFixed(2);
            depositInfo.textContent = `Deposit: €${totalDeposit}`;
        }
    }

    /**
     * Start auto-updating campaign status
     */
    _startAutoUpdate() {
        // Initial update
        this._updateCampaignStatus();

        // Set interval for updates
        this.updateInterval = setInterval(() => {
            this._updateCampaignStatus();
        }, this.options.updateInterval);
    }

    /**
     * Update campaign status via AJAX
     */
    _updateCampaignStatus() {
        const url = this.options.statusUrl.replace('{id}', this.campaignId);

        this._client.get(url, (response) => {
            const data = JSON.parse(response);
            if (data.success) {
                this._updateProgressBar(data.campaign);
                this._updateStatistics(data.campaign);

                // Check if campaign ended
                if (!data.campaign.active) {
                    this._showCampaignEnded();
                    clearInterval(this.updateInterval);
                }
            }
        });
    }

    /**
     * Update progress bar
     */
    _updateProgressBar(campaign) {
        const progressBar = this.el.querySelector('.progress-bar');
        if (!progressBar) return;

        const progress = campaign.targetQuantity > 0
            ? (campaign.currentQuantity / campaign.targetQuantity * 100)
            : 0;

        progressBar.style.width = `${Math.min(progress, 100)}%`;
        progressBar.setAttribute('aria-valuenow', progress);

        // Update text
        const progressInfo = this.el.querySelector('.progress-info');
        if (progressInfo) {
            progressInfo.innerHTML = `
                <span>${campaign.currentQuantity} / ${campaign.targetQuantity} pledged</span>
                <span>${progress.toFixed(1)}%</span>
            `;
        }

        // Add success class if target reached
        if (progress >= 100) {
            progressBar.classList.add('bg-success');
            this._showTargetReached();
        }
    }

    /**
     * Update campaign statistics
     */
    _updateStatistics(campaign) {
        const statsElement = this.el.querySelector('.campaign-stats');
        if (!statsElement) return;

        // Update various statistics
        const backersEl = statsElement.querySelector('[data-backers]');
        const revenueEl = statsElement.querySelector('[data-revenue]');

        if (backersEl) backersEl.textContent = campaign.currentQuantity;
        if (revenueEl) revenueEl.textContent = `€${campaign.currentRevenue.toFixed(2)}`;
    }

    /**
     * Show target reached message
     */
    _showTargetReached() {
        const successBanner = document.createElement('div');
        successBanner.className = 'alert alert-success campaign-success-banner';
        successBanner.innerHTML = `
            <i class="icon icon-check-circle"></i>
            <strong>Target Reached!</strong> This campaign has successfully reached its funding goal!
        `;

        const widget = this.el.querySelector('.crowd-campaign-widget');
        if (widget && !widget.querySelector('.campaign-success-banner')) {
            widget.insertBefore(successBanner, widget.firstChild);
        }
    }

    /**
     * Initialize social share buttons
     */
    _initializeShareButtons() {
        const shareButtons = this.el.querySelectorAll('[data-share]');

        shareButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const network = button.dataset.share;
                const url = encodeURIComponent(window.location.href);
                const title = encodeURIComponent(document.title);

                let shareUrl;
                switch(network) {
                    case 'facebook':
                        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                        break;
                    case 'twitter':
                        shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                        break;
                    case 'linkedin':
                        shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                        break;
                    case 'email':
                        shareUrl = `mailto:?subject=${title}&body=${url}`;
                        break;
                }

                if (shareUrl) {
                    if (network === 'email') {
                        window.location.href = shareUrl;
                    } else {
                        window.open(shareUrl, 'share', 'width=600,height=400');
                    }
                }
            });
        });
    }

    /**
     * Show success message
     */
    _showSuccessMessage(message) {
        this._showMessage(message, 'success');
    }

    /**
     * Show error message
     */
    _showErrorMessage(message) {
        this._showMessage(message, 'danger');
    }

    /**
     * Show message
     */
    _showMessage(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} campaign-alert`;
        alert.textContent = message;

        this.el.insertBefore(alert, this.el.firstChild);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    /**
     * Clean up on destroy
     */
    destroy() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }

        super.destroy();
    }
}