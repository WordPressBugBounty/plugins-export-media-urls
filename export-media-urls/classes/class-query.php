<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

/**
 * Translates sanitized request options into WP_Query arguments for attachments.
 */
class EMU_Query
{
    /**
     * @param array $o Sanitized options (see EMU_Request::from_post()).
     * @return array WP_Query arguments.
     */
    public static function build($o)
    {
        $post_author    = ($o['post_author'] === 'all') ? '' : $o['post_author'];
        $posts_per_page = ($o['post_per_page'] === 'all') ? -1 : (int) $o['post_per_page'];
        $offset         = ($o['offset'] === 'all') ? '' : (int) $o['offset'];

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'author'         => $post_author,
            'posts_per_page' => $posts_per_page,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );

        // Media type → MIME filter. WordPress accepts a top-level type ("image")
        // which matches every "image/*" subtype.
        $mime = self::mime_for($o['media_type']);
        if ($mime !== '') {
            $args['post_mime_type'] = $mime;
        }

        // Attached vs unattached (orphaned) media.
        if ($o['attachment_status'] === 'attached') {
            // post_parent > 0 — WP_Query has no native operator, so filter via a
            // meta-less approach: query all and let post_parent__not_in handle 0.
            $args['post_parent__not_in'] = array(0);
        } elseif ($o['attachment_status'] === 'unattached') {
            $args['post_parent'] = 0;
        }

        if ($o['date_range'] === 'range' && $o['start_date'] !== '' && $o['end_date'] !== '') {
            $args['date_query'] = array(
                array(
                    'after'     => $o['start_date'],
                    'before'    => $o['end_date'],
                    'inclusive' => true,
                ),
            );
        }

        return $args;
    }

    /**
     * Map the UI media-type choice to a WP_Query post_mime_type value.
     *
     * @param string $type
     * @return string|array Empty string means "no MIME restriction"; an array
     *                      lists explicit MIME types to match.
     */
    private static function mime_for($type)
    {
        switch ($type) {
            case 'image':
            case 'video':
            case 'audio':
                return $type;
            case 'document':
                return array(
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                    'text/csv',
                    'application/rtf',
                );
            case 'archive':
                return array('application/zip', 'application/x-rar-compressed', 'application/x-tar', 'application/gzip', 'application/x-7z-compressed');
            case 'all':
            default:
                return '';
        }
    }
}
