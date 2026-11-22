/**
 * A picture on a message, a leaflet or an event comment, as the server
 * sends it (App\Models\Picture::presentation): its address at full size, a
 * smaller copy for grids (the same address when it is small itself) and
 * its size, so a page can leave room for it before it loads.
 */
export type Picture = {
    id: number;
    url: string;
    thumb_url: string;
    width: number | null;
    height: number | null;
};

/** How many pictures one post may carry (Picture::MAX_PER_POST). */
export const MAX_PICTURES = 10;

/** The longest edge a picture is sent at; the server keeps the same. */
export const PICTURE_MAX_EDGE = 1600;

/**
 * The most one prepared picture may weigh. The server takes 10 MB a
 * request (deploy/php-fpm), so ten pictures have to fit in that together.
 */
export const PICTURE_MAX_BYTES = 800_000;
