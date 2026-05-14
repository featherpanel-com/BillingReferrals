/**
 * BillingReferrals - Referral Tracking Script
 * 
 * This script handles referral code tracking on the registration page.
 * It reads the ?ref= parameter from the URL and sets a cookie via the API.
 * 
 * This is NOT injected directly into the panel HTML - it's loaded as a
 * separate script file by FeatherPanel's plugin system.
 */

(function() {
    'use strict';

    const REFERRAL_API_ENDPOINT = '/api/billingreferrals/visit';
    const REFERRAL_PARAM = 'ref';

    /**
     * Get URL parameters
     */
    function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    /**
     * Check if we're on the registration page
     */
    function isRegistrationPage() {
        const path = window.location.pathname;
        return path.includes('/register') || path.includes('/signup') || path.includes('/auth/register');
    }

    /**
     * Track referral visit via API
     */
    async function trackReferralVisit(code) {
        try {
            const response = await fetch(REFERRAL_API_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code: code }),
            });

            if (!response.ok) {
                console.warn('[BillingReferrals] Failed to track referral visit:', response.status);
                return false;
            }

            const data = await response.json();
            
            if (data.success) {
                console.log('[BillingReferrals] Referral visit tracked successfully');
                
                // Store in sessionStorage to show a welcome message
                sessionStorage.setItem('billingreferrals_pending', JSON.stringify({
                    code: code,
                    referrer_credits: data.data?.referrer_credits || 0,
                    referee_credits: data.data?.referee_credits || 0,
                }));
                
                return true;
            }
        } catch (error) {
            console.error('[BillingReferrals] Error tracking referral:', error);
        }
        
        return false;
    }

    /**
     * Display referral info on registration page
     */
    function showReferralBanner() {
        const pendingData = sessionStorage.getItem('billingreferrals_pending');
        if (!pendingData) return;

        try {
            const data = JSON.parse(pendingData);
            
            // Create banner element
            const banner = document.createElement('div');
            banner.id = 'billingreferrals-banner';
            banner.style.cssText = `
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            banner.innerHTML = `
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 4px;">
                    🎁 You've been invited!
                </div>
                <div style="font-size: 14px; opacity: 0.9;">
                    Sign up now and get <strong>${data.referee_credits} credits</strong> bonus!
                </div>
            `;

            // Try to find a good place to insert the banner
            const formContainer = document.querySelector('form')?.parentElement;
            const mainContainer = document.querySelector('.card, .panel, .container, main, #app');
            const target = formContainer || mainContainer || document.body;
            
            if (target.firstChild) {
                target.insertBefore(banner, target.firstChild);
            } else {
                target.appendChild(banner);
            }

            // Clear the pending data so it doesn't show again
            sessionStorage.removeItem('billingreferrals_pending');
        } catch (e) {
            console.error('[BillingReferrals] Error showing banner:', e);
        }
    }

    /**
     * Initialize referral tracking
     */
    function init() {
        // Check for referral code in URL
        const refCode = getUrlParam(REFERRAL_PARAM);
        
        if (refCode) {
            // Track the visit
            trackReferralVisit(refCode).then(() => {
                // Clean up URL (remove ref parameter)
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete(REFERRAL_PARAM);
                    window.history.replaceState({}, document.title, url.toString());
                }
            });
        }

        // Show banner if on registration page and we have pending data
        if (isRegistrationPage()) {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', showReferralBanner);
            } else {
                showReferralBanner();
            }
        }
    }

    // Run initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
