<?php declare(strict_types=1);

namespace Reconmap\Controllers\Users;

use Psr\Http\Message\ServerRequestInterface;
use Reconmap\Controllers\Controller;
use Reconmap\Models\AuditLogAction;
use Reconmap\Repositories\UserRepository;
use Reconmap\Services\ApplicationConfig;
use Reconmap\Services\AuditLogService;
use Reconmap\Services\PasswordGenerator;
use Reconmap\Services\RedisServer;

class CreateUserController extends Controller
{
    public function __construct(
        private UserRepository $userRepository,
        private RedisServer $redisServer,
        private PasswordGenerator $passwordGenerator,
        private ApplicationConfig $applicationConfig
    )
    {
    }

    public function __invoke(ServerRequestInterface $request, array $args): array
    {
        $user = $this->getJsonBodyDecoded($request);

        $passwordGenerationMethodIsAuto = empty($user->unencryptedPassword);
        if ($passwordGenerationMethodIsAuto) {
            $user->unencryptedPassword = $this->passwordGenerator->generate(24);
        }

        $user->password = password_hash($user->unencryptedPassword, PASSWORD_DEFAULT);

        $userId = $this->userRepository->create($user);

        $loggedInUserId = $request->getAttribute('userId');

        $this->auditAction($loggedInUserId, $userId);

        $instanceUrl = $this->applicationConfig->getSettings('cors')['allowedOrigins'][0];

        if ($passwordGenerationMethodIsAuto || (bool)($user->sendEmailToUser)) {
            $emailBody = $this->template->render('users/newAccount', [
                'instance_url' => $instanceUrl,
                'user' => (array)$user,
                'unencryptedPassword' => $user->unencryptedPassword
            ]);

            $result = $this->redisServer->lPush("email:queue",
                json_encode([
                    'subject' => 'Account created',
                    'to' => [$user->email => $user->name],
                    'body' => $emailBody
                ])
            );
            if (false === $result) {
                $this->logger->error('Item could not be pushed to the queue', ['queue' => 'email:queue']);
            }
        } else {
            $this->logger->debug('Email invitation not sent', ['email' => $user->email]);
        }

        return (array)$user;
    }

    private function auditAction(int $loggedInUserId, int $userId): void
    {
        $auditLogService = new AuditLogService($this->db);
        $auditLogService->insert($loggedInUserId, AuditLogAction::USER_CREATED, ['type' => 'user', 'id' => $userId]);
    }
}
