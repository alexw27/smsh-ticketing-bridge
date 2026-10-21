<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

final class WeChatPhoneNumberExchangeException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $wechatErrcode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getWechatErrcode(): ?int
    {
        return $this->wechatErrcode;
    }

    /**
     * @return array<string, int>
     */
    public function getDetails(): array
    {
        if ($this->wechatErrcode === null) {
            return [];
        }

        return ['wechat_errcode' => $this->wechatErrcode];
    }
}
