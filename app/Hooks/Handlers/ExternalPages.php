<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Modules\CustomerPortal\PortalService;
use FluentSupport\App\Services\EmailClaimService;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Storage\StorageManager;
use FluentSupport\Framework\Support\Arr;

/**
 * Public ticket, attachment and email-claim pages.
 */
class ExternalPages
{
    public function route()
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_METHOD is server-controlled, sanitized for comparison only
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ($requestMethod !== 'GET') {
            wp_die(esc_html__('Invalid request method', 'fluent-support'), esc_html__('Method Not Allowed', 'fluent-support'), ['response' => 405]);
        }

        $this->checkRateLimit();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        if (!isset($_REQUEST['fs_view'])) {
            wp_die(esc_html__('Missing required parameter', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        $route = isset($_REQUEST['fs_view']) ? sanitize_text_field(wp_unslash($_REQUEST['fs_view'])) : '';

        if (empty($route)) {
            wp_die(esc_html__('Missing required parameter', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        $methodMaps = [
            'ticket'      => 'handleTicketView',
            'email_claim' => 'handleEmailClaim'
        ];

        if (isset($methodMaps[$route])) {
            $this->verifyPublicEndpointSecurity($route);
            $this->{$methodMaps[$route]}();
        } else {
            wp_die(esc_html__('Invalid route', 'fluent-support'), esc_html__('Not Found', 'fluent-support'), ['response' => 404]);
        }
    }

    /**
     * Request or confirm moving the support address to the WP account's email.
     *
     * `fs_claim_action=send` sends mail, so it needs a nonce. `fs_claim=<token>` is
     * the signed link from that mail and still requires the requesting account.
     *
     * @return void
     */
    public function handleEmailClaim()
    {
        $baseUrl = Helper::getPortalBaseUrl();

        if (!$baseUrl) {
            wp_die(esc_html__('Invalid route', 'fluent-support'), esc_html__('Not Found', 'fluent-support'), ['response' => 404]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the send branch verifies a nonce and the confirm branch verifies a signature
        $action = isset($_GET['fs_claim_action']) ? sanitize_text_field(wp_unslash($_GET['fs_claim_action'])) : '';

        if ($action === 'send') {
            $this->handleEmailClaimRequest();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is a signed secret delivered to the address being claimed
        $token = isset($_GET['fs_claim']) ? sanitize_text_field(wp_unslash($_GET['fs_claim'])) : '';

        if (!$token) {
            $this->redirectToPortal('invalid');
        }

        if (!get_current_user_id()) {
            // The mailbox alone isn't enough proof, so require login too.
            $returnUrl = add_query_arg([
                'fs_view'  => 'email_claim',
                'fs_claim' => $token
            ], $baseUrl);

            wp_safe_redirect(wp_login_url($returnUrl));
            exit;
        }

        $resolved = EmailClaimService::resolveClaim($token);

        if ($resolved['status'] !== 'ok') {
            $this->redirectToPortal($resolved['status']);
        }

        EmailClaimService::apply($resolved['customer'], $resolved['email']);

        $this->redirectToPortal('confirmed');
    }

    /**
     * @return void
     */
    protected function handleEmailClaimRequest()
    {
        if (!get_current_user_id()) {
            $this->redirectToPortal('invalid');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, 'fs_email_claim_send')) {
            $this->redirectToPortal('invalid');
        }

        $divergence = EmailClaimService::getDivergence();

        if (!$divergence) {
            $this->redirectToPortal('nothing_to_do');
        }

        $error = EmailClaimService::issue($divergence);

        $this->redirectToPortal($error ?: 'sent');
    }

    /**
     * @param string $status
     * @return void
     */
    protected function redirectToPortal($status)
    {
        wp_safe_redirect(add_query_arg('fs_claim_result', $status, Helper::getPortalBaseUrl()));
        exit;
    }

    public function handleTicketView()
    {
        if (!Helper::isPublicSignedTicketEnabled()) {
            $this->handleInvalidTicket();
        } else {
            $this->handleValidTicket();
        }
    }

    /**
     * Serve an attachment by its `fst_file` hash and signature.
     *
     * @return void
     * @since 3.2.0
     */
    public function view_attachment()
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_METHOD is server-controlled, sanitized for comparison only
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ($requestMethod !== 'GET') {
            wp_die(esc_html__('Invalid request method', 'fluent-support'), esc_html__('Method Not Allowed', 'fluent-support'), ['response' => 405]);
        }

        $this->checkRateLimit();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        if (!isset($_REQUEST['fst_file'])) {
            wp_die(esc_html__('Missing required parameter', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        $attachmentHash = isset($_REQUEST['fst_file']) ? sanitize_text_field(wp_unslash($_REQUEST['fst_file'])) : '';

        if (empty($attachmentHash)) {
            wp_die(esc_html__('Invalid attachment hash.', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        $attachment = $this->getAttachmentByHash($attachmentHash);

        // Rows waiting for the delete job belong to deleted tickets and replies.
        if (!$attachment || $attachment->status === StorageManager::STATUS_DELETING) {
            wp_die(esc_html__('Invalid attachment hash.', 'fluent-support'), esc_html__('Not Found', 'fluent-support'), ['response' => 404]);
        }

        // Inline (pasted) images skip the signature because they are already embedded in emails.
        // A missing or expired signature (e.g. an old email) falls back to who is opening it.
        if ($attachment->status !== 'inline' && !$this->validateAttachmentSignature($attachment)) {
            if (!$this->canViewWithoutSignature($attachment)) {
                if (!get_current_user_id()) {
                    $this->redirectToLogin($attachment);
                }

                $dieMessage = esc_html__('Sorry, you do not have access to this file.', 'fluent-support');
                wp_die(esc_html($dieMessage), esc_html__('Forbidden', 'fluent-support'), ['response' => 403]);
            }

            // Served by who is asking, not by the URL: keep shared caches out of it.
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0');
        }

        if ('local' !== $attachment->driver) {
            StorageManager::serve($attachment);
        }

        if (!file_exists($attachment->file_path)) {
            die('File could not be found');
        }
        $this->serveLocalAttachment($attachment);
    }

    private function getAttachmentByHash($attachmentHash)
    {
        return Attachment::where('file_hash', $attachmentHash)->first();
    }

    private function validateAttachmentSignature($attachment)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        if (!isset($_REQUEST['secure_sign'])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        $secureSign = isset($_REQUEST['secure_sign']) ? sanitize_text_field(wp_unslash($_REQUEST['secure_sign'])) : '';

        if (empty($secureSign)) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        $expires = isset($_REQUEST['expires']) ? sanitize_text_field(wp_unslash($_REQUEST['expires'])) : '';

        if ($expires === '') {
            // Legacy URL signed for the current UTC hour only.
            return hash_equals(Attachment::legacySignature($attachment->id, gmdate('YmdH')), $secureSign);
        }

        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        // Email links are also bound to the ticket hash, so rotating it (customer change) revokes them.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses signature validation instead of nonces
        if (isset($_REQUEST['sv']) && $_REQUEST['sv'] === '3') {
            $ticketHash = $attachment->ticket_id ? (string) Ticket::where('id', $attachment->ticket_id)->value('hash') : '';
            return hash_equals(Attachment::ticketSignature($attachment->id, (int) $expires, $ticketHash), $secureSign);
        }

        return hash_equals(Attachment::signature($attachment->id, (int) $expires), $secureSign);
    }

    /**
     * Access check for a link whose signature is missing or has expired:
     *  - an agent who can access the ticket (any attachment, notes included);
     *  - the customer who owns the ticket and may still use the portal, or a matching
     *    hash-signed ticket link when those are on, for attachments customers can see;
     *  - the uploader, for a temporary upload (not on a ticket yet, or on a reply or
     *    note still being written).
     */
    private function canViewWithoutSignature($attachment)
    {
        $ticket = $attachment->ticket_id ? Ticket::find($attachment->ticket_id) : null;

        if ($attachment->ticket_id && !$ticket) {
            return false;
        }

        if ($ticket && get_current_user_id() && Helper::getCurrentAgent()) {
            return PermissionManager::canAccessTicket($ticket);
        }

        // Not on a ticket yet, or a reply or note still being written: only the uploader.
        if (!$ticket || $attachment->status === 'in-active') {
            $person = Helper::getCurrentPerson();
            return $person && $attachment->person_id && $attachment->person_id == $person->id;
        }

        if (!$this->isVisibleToCustomer($attachment)) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        $ticketHash = isset($_REQUEST['fst_ticket']) ? sanitize_text_field(wp_unslash($_REQUEST['fst_ticket'])) : '';
        if ($ticketHash && $ticket->hash && Helper::isPublicSignedTicketEnabled()
            && hash_equals((string) $ticket->hash, $ticketHash)) {
            return true;
        }

        $customer = get_current_user_id() ? Helper::getCurrentCustomer() : null;
        if (!$customer || $ticket->customer_id != $customer->id) {
            return false;
        }

        // Same rules as the portal (blocked customers, add-on filters).
        try {
            PortalService::assertCanAccess($customer, $ticket);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ticket-level files and files on replies customers see; never notes or drafts.
     */
    private function isVisibleToCustomer($attachment)
    {
        if (!$attachment->conversation_id) {
            return true;
        }

        $type = Conversation::where('id', $attachment->conversation_id)
            ->where('ticket_id', $attachment->ticket_id)
            ->value('conversation_type');

        return in_array($type, ['response', 'ticket_merge_activity', 'ticket_split_activity'], true);
    }

    private function redirectToLogin($attachment)
    {
        // Back to the same file; after login the access check lets them in.
        $currentUrl = add_query_arg(['fst_file' => $attachment->file_hash], site_url('/index.php'));

        /**
         * Where to send a logged-out visitor whose attachment link has expired.
         *
         * @param string $loginUrl
         * @param string $currentUrl The attachment URL to return to.
         */
        $loginUrl = apply_filters('fluent_support/attachment_login_url', wp_login_url($currentUrl), $currentUrl);

        wp_safe_redirect($loginUrl);
        exit();
    }

    private function handleInvalidTicket()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        if (!isset($_REQUEST['ticket_id'])) {
            wp_die(esc_html__('Missing ticket ID parameter', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        $ticketId = isset($_REQUEST['ticket_id']) ? absint($_REQUEST['ticket_id']) : 0;

        if ($ticketId <= 0) {
            wp_die(esc_html__('Invalid ticket ID', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        $ticket = Ticket::wherePublicIdentifier($ticketId)->first();

        if (!$ticket) {
            $this->showInvalidPortalMessage();
        } else {
            $this->redirectToTicketView($ticket);
        }
    }

    private function handleValidTicket()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        if (!isset($_REQUEST['support_hash']) || !isset($_REQUEST['ticket_id'])) {
            wp_die(esc_html__('Missing required parameters', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        $ticketHash = isset($_REQUEST['support_hash']) ? sanitize_text_field(wp_unslash($_REQUEST['support_hash'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
        $ticketId = isset($_REQUEST['ticket_id']) ? absint($_REQUEST['ticket_id']) : 0;

        if (empty($ticketHash) || !preg_match('/^[a-zA-Z0-9]+$/', $ticketHash)) {
            wp_die(esc_html__('Invalid ticket hash format', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        if ($ticketId <= 0) {
            wp_die(esc_html__('Invalid ticket ID', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
        }

        $ticket = Ticket::where('hash', $ticketHash)
            ->wherePublicIdentifier($ticketId)
            ->first();

        if (!$ticket) {
            $this->showInvalidPortalMessage();
        } elseif (get_current_user_id()) {
            $this->redirectToTicketView($ticket);
        }
        // Logged-out visitors stay on the signed URL; the portal app reads the hash from it.
    }

    private function showInvalidPortalMessage()
    {
        echo '<h3 style="text-align: center; margin: 50px 0;">' . esc_html__('Invalid Support Portal URL', 'fluent-support') . '</h3>';
        die();
    }

    private function redirectToTicketView($ticket)
    {
        $redirectUrl = Helper::getTicketViewUrl($ticket);
        $this->redirectToExternalAttachment($redirectUrl);
    }

    private function redirectToExternalAttachment($redirectUrl)
    {
        // Not wp_safe_redirect: remote attachments (Drive, Dropbox) live on other hosts.
        wp_redirect($redirectUrl, 307);
        exit();
    }

    private function serveLocalAttachment($attachment)
    {
        $file_path = realpath($attachment->file_path);
        $uploads     = wp_upload_dir();
        $uploads_dir = realpath($uploads['basedir']);

        if (!$file_path || !$uploads_dir || strpos($file_path, $uploads_dir) !== 0 || !file_exists($file_path)) {
            wp_die(esc_html__('File not found or access denied', 'fluent-support'), 403);
            return;
        }

        ob_get_clean();
        $original_user_agent = ini_get('user_agent');
        // phpcs:ignore WordPress.PHP.IniSet.Risky -- Temporary change for file serving, restored immediately after
        ini_set('user_agent', 'Fluent Support/' . FLUENT_SUPPORT_VERSION . '; ' . esc_url(get_bloginfo('url')));

        header("Content-Type: " . esc_attr($attachment->file_type));
        header("Content-Disposition: inline; filename=\"" . esc_attr($attachment->title) . "\"");

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file serving required for attachment download
        readfile($file_path);

        // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restoring original value
        ini_set('user_agent', $original_user_agent);
        die();
    }

    private function verifyPublicEndpointSecurity($route)
    {
        switch ($route) {
            case 'ticket':
                // support_hash is only required when signed ticket links are enabled.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public endpoint uses hash validation instead of nonces
                if (!isset($_REQUEST['ticket_id'])) {
                    wp_die(esc_html__('Missing ticket ID parameter', 'fluent-support'), esc_html__('Bad Request', 'fluent-support'), ['response' => 400]);
                }
                break;

            default:
                break;
        }
    }

    /**
     * 30 requests per minute per IP on the public endpoints.
     */
    private function checkRateLimit()
    {
        $ip = Helper::getIp();
        $transient_key = 'fs_rate_limit_' . wp_hash($ip);
        $requests = get_transient($transient_key);

        if ($requests === false) {
            set_transient($transient_key, 1, 60);
        } else {
            $requests++;
            if ($requests > 30) {
                wp_die(esc_html__('Rate limit exceeded. Please try again later.', 'fluent-support'), esc_html__('Too Many Requests', 'fluent-support'), ['response' => 429]);
            }
            set_transient($transient_key, $requests, 60);
        }
    }
}
