<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Services;

use Clesson\Silverstripe\Contacts\Models\Contact;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Fetches a Gravatar image for a Contact based on the linked Member's email address
 * and stores it as the Contact's Avatar.
 *
 * Usage (called from Contact::onAfterWrite()):
 *   $service = GravatarService::create();
 *   if ($service->fetchAndStoreAvatar($contact)) {
 *       $contact->write();
 *   }
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Services
 */
class GravatarService
{
    use Injectable;

    /**
     * Base URL for the Gravatar API.
     */
    private const GRAVATAR_BASE_URL = 'https://www.gravatar.com/avatar/';

    /**
     * Requested avatar size in pixels.
     */
    private const GRAVATAR_SIZE = 200;

    /**
     * HTTP request timeout in seconds.
     */
    private const GRAVATAR_TIMEOUT = 5;

    /**
     * Silverstripe assets folder path for storing downloaded avatars.
     */
    private const AVATAR_FOLDER = 'Contacts/Avatars';

    /**
     * Fetches the Gravatar image for the given Contact (if any) and sets its AvatarID.
     *
     * Returns true when a Gravatar was found and stored, and AvatarID has been updated
     * on the Contact object. The caller is responsible for persisting the Contact.
     *
     * @param Contact $contact The contact whose Gravatar should be fetched.
     * @return bool True if an avatar was fetched and AvatarID was updated.
     */
    public function fetchAndStoreAvatar(Contact $contact): bool
    {
        $email = $this->resolveEmail($contact);
        if ($email === null) {
            return false;
        }

        $hash = md5(strtolower(trim($email)));
        $url = self::GRAVATAR_BASE_URL . $hash . '?d=404&s=' . self::GRAVATAR_SIZE;

        $result = $this->downloadImage($url);
        if ($result === null) {
            return false;
        }

        [$content, $extension] = $result;

        $image = $this->storeImage($content, $hash, $extension);
        if ($image === null) {
            return false;
        }

        $contact->AvatarID = $image->ID;
        return true;
    }

    /**
     * Resolves the email address for the given Contact from its linked Member account.
     *
     * @param Contact $contact
     * @return string|null The email address, or null if not available.
     */
    private function resolveEmail(Contact $contact): ?string
    {
        $account = $contact->Account();
        if ($account === null || !$account->exists()) {
            return null;
        }

        $email = (string) $account->Email;
        return $email !== '' ? $email : null;
    }

    /**
     * Downloads the image from the given URL.
     *
     * Returns null if the request fails, times out, or the server responds with a
     * non-2xx status code (e.g. HTTP 404 when no Gravatar exists for the email).
     *
     * @param string $url The full Gravatar URL including query parameters.
     * @return array{0: string, 1: string}|null Tuple of [binaryContent, fileExtension], or null.
     */
    private function downloadImage(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => self::GRAVATAR_TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        // $http_response_header is a local variable populated by file_get_contents().
        $content = @file_get_contents($url, false, $context);

        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        $responseHeaders = $http_response_header ?? [];

        if ($content === false || $content === '' || !$this->isSuccessResponse($responseHeaders)) {
            return null;
        }

        return [$content, $this->resolveExtension($responseHeaders)];
    }

    /**
     * Checks whether the HTTP response headers indicate a 2xx success status.
     *
     * @param array<string> $headers The raw HTTP response headers.
     * @return bool
     */
    private function isSuccessResponse(array $headers): bool
    {
        if (empty($headers)) {
            return false;
        }

        // The first header line contains the status, e.g. "HTTP/1.1 200 OK".
        return (bool) preg_match('/^HTTP\/\d+(\.\d+)?\s+2\d\d/', $headers[0]);
    }

    /**
     * Resolves the file extension from the HTTP response Content-Type header.
     * Falls back to 'jpg' when the content type cannot be determined.
     *
     * @param array<string> $headers The raw HTTP response headers.
     * @return string File extension without leading dot (e.g. 'jpg', 'png', 'gif').
     */
    private function resolveExtension(array $headers): string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === false) {
                continue;
            }

            if (stripos($header, 'image/png') !== false) {
                return 'png';
            }

            if (stripos($header, 'image/gif') !== false) {
                return 'gif';
            }

            return 'jpg';
        }

        return 'jpg';
    }

    /**
     * Stores the given image binary data as a Silverstripe Image asset.
     *
     * The image is placed in the configured avatar folder and published immediately
     * so it is accessible on the frontend.
     *
     * @param string $content   Raw binary image data.
     * @param string $hash      MD5 hash of the email address, used as the filename.
     * @param string $extension File extension without leading dot.
     * @return Image|null The stored Image DataObject, or null on failure.
     */
    private function storeImage(string $content, string $hash, string $extension): ?Image
    {
        try {
            $folder = Folder::find_or_make(self::AVATAR_FOLDER);

            /** @var Image $image */
            $image = Image::create();
            $image->ParentID = $folder->ID;
            $image->setFromString($content, 'gravatar-' . $hash . '.' . $extension);
            $image->write();
            $image->publishSingle();

            return $image;
        } catch (\Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                'GravatarService: Failed to store avatar image — ' . $e->getMessage(),
                ['exception' => $e]
            );

            return null;
        }
    }
}



