<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\EmailTemplate;

/**
 * Rendu en HTML compatible Gmail/Outlook/Apple Mail : mise en page par
 * tableau, styles toujours en ligne (jamais de flexbox/grid, mal ou pas
 * supportés par les moteurs de rendu des clients de bureau — Outlook en
 * tête). Le test Litmus (ou équivalent) demandé par le ticket reste un
 * contrôle visuel humain, hors de portée d'un test automatisé.
 */
final class RenderEmailTemplate
{
    public function __construct(
        private readonly ResolveMergeVariables $resolveMergeVariables,
    ) {}

    public function render(EmailTemplate $template, Contact $contact, ?Event $event): string
    {
        $rows = collect($template->blocks)
            ->map(fn (array $block): string => $this->renderBlock($block, $contact, $event))
            ->implode('');

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;">'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>';
    }

    public function renderSubject(EmailTemplate $template, Contact $contact, ?Event $event): string
    {
        return $this->resolveMergeVariables->resolve($template->subject, $contact, $event);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderBlock(array $block, Contact $contact, ?Event $event): string
    {
        $resolve = fn (string $text): string => $this->resolveMergeVariables->resolve($text, $contact, $event);

        return match ($block['type'] ?? null) {
            'heading' => '<tr><td style="padding:12px 0;font-size:22px;font-weight:600;color:#1b1611;">'
                .e($resolve((string) ($block['text'] ?? ''))).'</td></tr>',
            'text' => '<tr><td style="padding:8px 0;font-size:15px;line-height:1.5;color:#1b1611;">'
                .$resolve((string) ($block['html'] ?? '')).'</td></tr>',
            'image' => '<tr><td style="padding:8px 0;"><img src="'.e($resolve((string) ($block['url'] ?? '')))
                .'" alt="'.e($resolve((string) ($block['alt'] ?? ''))).'" style="max-width:100%;display:block;"></td></tr>',
            'button' => '<tr><td style="padding:16px 0;"><a href="'.e($resolve((string) ($block['url'] ?? '#')))
                .'" style="background:#1b1611;color:#ffffff;padding:12px 24px;border-radius:999px;text-decoration:none;display:inline-block;font-size:14px;">'
                .e($resolve((string) ($block['text'] ?? ''))).'</a></td></tr>',
            'divider' => '<tr><td style="padding:8px 0;"><hr style="border:none;border-top:1px solid #e3e3e0;"></td></tr>',
            'spacer' => '<tr><td style="height:'.max(0, (int) ($block['height'] ?? 16)).'px;line-height:1px;font-size:1px;">&nbsp;</td></tr>',
            default => '',
        };
    }
}
