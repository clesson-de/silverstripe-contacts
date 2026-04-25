<?php

declare(strict_types=1);

namespace Clesson\Contacts\Controllers;

use Clesson\Contacts\Models\Address;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Controller for dynamic address-related requests.
 *
 * Provides AJAX endpoints for dependent dropdowns (e.g. Region based on Country).
 *
 * @package Clesson\Contacts
 * @subpackage Controllers
 */
class AddressController extends Controller
{

    /**
     * @inheritdoc
     */
    private static array $allowed_actions = [
        'regions',
    ];

    /**
     * Returns subdivisions (regions/states) for a given country as JSON.
     *
     * URL example: /contacts-api/address/regions?country=US&locale=en
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function regions(HTTPRequest $request): HTTPResponse
    {
        // Require CMS access
        if (!Permission::check('CMS_ACCESS', 'any', Security::getCurrentUser())) {
            return $this->jsonResponse(['error' => 'Access denied'], 403);
        }

        $countryCode = $request->getVar('country');
        $locale = $request->getVar('locale');

        if (!$countryCode) {
            return $this->jsonResponse(['error' => 'Missing country parameter'], 400);
        }

        $options = Address::getSubdivisionOptions((string)$countryCode, $locale);

        return $this->jsonResponse([
            'options' => $options,
            'debug' => [
                'country' => $countryCode,
                'locale' => $locale,
                'count' => count($options),
            ],
        ]);
    }

    /**
     * Returns a JSON response with the given data and status code.
     *
     * @param array $data
     * @param int   $statusCode
     * @return HTTPResponse
     */
    protected function jsonResponse(array $data, int $statusCode = 200): HTTPResponse
    {
        /** @var HTTPResponse $response */
        $response = HTTPResponse::create();
        $response->setStatusCode($statusCode);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data));

        return $response;
    }

}

