<?php

namespace OCA\Appointments\Backend;

/**
 * Available placeholders for custom booking, confirmation, cancellation,
 * update, reminder and organizer email HTML bodies and subjects.
 *
 * This class only prepares text values. HtmlTemplate escapes substitutions
 * and sanitizes HTML when rendering the message.
 */
final class MailTemplateVariables
{
    public static function build(
        string $name,
        string $organization,
        string $dateTime,
        string $attendeeEmail,
        string $organizationEmail,
        string $organizationPhone,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $timeZone,
        $l10n,
        string $location = '',
        string $attendeePhone = '',
        bool $attendeeNameMissing = false
    ): array {
        // The booking form contains a single free-text name field. Do not try
        // to guess first names or family names from its contents.

        [$date, $time, $endTime] = self::formatParts($start, $end, $timeZone, $l10n);

        // An explicit booking flag is more accurate than treating the word
        // "Guest" as necessarily anonymous (it might be someone's name).
        $displayName = $attendeeNameMissing ? '' : $name;
        $salutation = $attendeeNameMissing
            ? $l10n->t('Hello,')
            : $l10n->t('Dear %s,', [$displayName]);

        return [
            'name' => $displayName,
            // Backwards compatibility with HTML templates saved before 2.8.5.10:
            // the legacy placeholder now means the exact entered name too.
            'firstName' => $displayName,
            'salutation' => $salutation,
            'email' => $attendeeEmail,
            'attendeePhone' => $attendeePhone,
            'dateTime' => $dateTime,
            'date' => $date,
            'time' => $time,
            'endTime' => $endTime,
            'durationMinutes' => (string)max(0, (int)round(
                ($end->getTimestamp() - $start->getTimestamp()) / 60
            )),
            'location' => $location,
            'organization' => $organization,
            'organizationEmail' => $organizationEmail,
            'phone' => $organizationPhone,
        ];
    }

    private static function formatParts(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $timeZone,
        $l10n
    ): array {
        // Floating iCalendar times have no TZID and must keep their wall time.
        if (str_starts_with($timeZone, 'F')) {
            $dateValue = $start->format('Ymd\THis');
            $timeValue = $dateValue;
            $endValue = $end->format('Ymd\THis');
        } else {
            $zoneName = str_starts_with($timeZone, 'T') ? substr($timeZone, 1) : $timeZone;
            try {
                $tz = new \DateTimeZone($zoneName);
                $start = $start->setTimezone($tz);
                $end = $end->setTimezone($tz);
            } catch (\Throwable $e) {
                // Keep the original event timezone if the supplied ID is invalid.
            }
            $dateValue = $start;
            $timeValue = $start;
            $endValue = $end;
        }

        return [
            $l10n->l('date', $dateValue, ['width' => 'long']),
            $l10n->l('time', $timeValue, ['width' => 'short']),
            $l10n->l('time', $endValue, ['width' => 'short'])
        ];
    }
}
