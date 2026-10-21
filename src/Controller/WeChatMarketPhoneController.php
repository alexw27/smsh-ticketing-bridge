<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Controller;

use App\Core\Api\Http\ApiErrorCode;
use App\Core\Api\Http\ApiResponseFactory;
use App\Core\Api\Normalizer\UserNormalizer;
use App\Core\Auth\Entity\User;
use Smsh\TicketingBridge\Api\ApplyWeChatMarketPhoneToUser;
use Smsh\TicketingBridge\Api\WeChatMarketPhoneApiPayload;
use Smsh\TicketingBridge\WeChat\ResolveWeChatMarketAccessToken;
use Smsh\TicketingBridge\WeChat\WeChatPhoneNumberExchangeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * SmartShanghai-only: exchange a WeChat getPhoneNumber code for the signed-in user's phone.
 */
final class WeChatMarketPhoneController extends AbstractController
{
    public function __construct(
        private readonly ApplyWeChatMarketPhoneToUser $applyWeChatMarketPhoneToUser,
        private readonly ApiResponseFactory $apiResponseFactory,
        private readonly UserNormalizer $userNormalizer,
    ) {
    }

    #[Route(path: '/api/v1/auth/external/wechat-market/phone', name: 'smsh_api_v1_auth_wechat_market_phone', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::AUTHENTICATION_REQUIRED,
                'Authentication is required to access this resource.',
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::INVALID_JSON,
                'Request body must be a JSON object.',
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        try {
            $payload = WeChatMarketPhoneApiPayload::fromRequestBody($body);
        } catch (\InvalidArgumentException $exception) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::VALIDATION_FAILED,
                'Validation failed.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                [$exception->getMessage() => 'Must be a string.'],
            );
        } catch (\DomainException $exception) {
            return $this->mapDomainException($exception);
        }

        try {
            $phone = $this->applyWeChatMarketPhoneToUser->handle($user, $payload);
        } catch (\DomainException $exception) {
            return $this->mapDomainException($exception);
        } catch (WeChatPhoneNumberExchangeException $exception) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::WECHAT_PHONE_EXCHANGE_FAILED,
                $exception->getMessage(),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getDetails(),
            );
        }

        $verifiedAt = $user->getPhoneVerifiedAt();

        return $this->apiResponseFactory->success([
            'phone' => $phone->phoneNumber,
            'phoneVerifiedAt' => $verifiedAt?->format(\DateTimeInterface::ATOM),
            'user' => $this->userNormalizer->normalize($user, UserNormalizer::FORMAT_API),
        ]);
    }

    private function mapDomainException(\DomainException $exception): JsonResponse
    {
        return match ($exception->getMessage()) {
            WeChatMarketPhoneApiPayload::ERROR_CODE_REQUIRED => $this->apiResponseFactory->error(
                ApiErrorCode::VALIDATION_FAILED,
                'Validation failed.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'Required.'],
            ),
            ResolveWeChatMarketAccessToken::ERROR_NOT_CONFIGURED => $this->apiResponseFactory->error(
                ApiErrorCode::WECHAT_MARKET_NOT_CONFIGURED,
                'The wechat-market integration is not configured.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ),
            default => throw $exception,
        };
    }
}
