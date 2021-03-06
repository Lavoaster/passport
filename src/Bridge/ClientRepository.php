<?php

namespace LaravelDoctrine\Passport\Bridge;

use LaravelDoctrine\Passport\Repositories\ClientRepository as DoctrineClientRepository;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * The client repository.
     *
     * @var DoctrineClientRepository
     */
    protected $clientRepository;

    /**
     * Create a new repository instance.
     *
     * @param  DoctrineClientRepository  $clientRepository
     */
    public function __construct(DoctrineClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier, $grantType,
                                    $clientSecret = null, $mustValidateSecret = true)
    {
        // First, we will verify that the client exists and is authorized to create personal
        // access tokens. Generally personal access tokens are only generated by the user
        // from the main interface. We'll only let certain clients generate the tokens.
        $record = $this->clientRepository->findActive($clientIdentifier);

        if (! $record || ! $this->handlesGrant($record, $grantType)) {
            return null;
        }

        // Once we have an existing client record we will create this actual client instance
        // and verify the secret if necessary. If the secret is valid we will be ready to
        // return this client instance back out to the consuming methods and finish up.
        $client = new Client(
            $clientIdentifier, $record->getName(), $record->getRedirect()
        );

        if ($mustValidateSecret &&
            ! hash_equals($record->getSecret(), (string) $clientSecret)) {
            return null;
        }

        return $client;
    }

    /**
     * Determine if the given client can handle the given grant type.
     *
     * @param  \LaravelDoctrine\Passport\Entities\Client  $record
     * @param  string  $grantType
     * @return bool
     */
    protected function handlesGrant($record, $grantType)
    {
        switch ($grantType) {
            case 'authorization_code':
                return ! $record->isFirstParty();
            case 'personal_access':
                return $record->isPersonalAccessClient();
            case 'password':
                return $record->isPasswordClient();
            default:
                return true;
        }
    }
}
