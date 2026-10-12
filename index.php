<?php

/* By Abdullah As-Sadeed */

declare(strict_types=1);

ini_set("default_charset", "UTF-8");

error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");

function clear_dom_node(DOMNode $dom_node): void
{
    while ($dom_node->firstChild) {
        $dom_node->removeChild($dom_node->firstChild);
    }
}

function get_base_url(): string
{
    if (
        (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (!empty($_SERVER["REQUEST_SCHEME"]) &&
            $_SERVER["REQUEST_SCHEME"] === "https") ||
        (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"]) &&
            $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https")
    ) {
        $scheme = "https";
    } else {
        $scheme = "http";
    }

    $host_name = filter_var(
        strtolower(trim($_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"])),
        FILTER_SANITIZE_FULL_SPECIAL_CHARS
    );

    $directory = rtrim(
        str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "")),
        "/."
    );

    $base_url = $scheme . "://" . $host_name . $directory . "/";

    return $base_url;
}

function generate_slug(string $title): string
{
    $slug = iconv("UTF-8", "ASCII//TRANSLIT", $title);

    if ($slug === false) {
        $slug = $title;
    }

    $slug = strtolower($slug);
    $slug = preg_replace("/[^a-z0-9]+/", "-", $slug);
    $slug = trim($slug, "-");

    return $slug;
}

function minify_html(string $html): string
{
    $minified_html = preg_replace("/\s+/", " ", $html); // Replace Consecutive Whitespace Characters with " "
    $minified_html = preg_replace("/>\s+</s", "><", $minified_html); # Remove Whitespace Between Tags
    $minified_html = trim($minified_html);

    return $minified_html;
}

function minify_css(string $css): string
{
    $minified_css = preg_replace("/\s+/", " ", $css); // Replace Consecutive Whitespace Characters with " "
    $minified_css = preg_replace("/\s*([{:;},])\s*/", '$1', $minified_css); // Remove Whitespace Around Symbols
    $minified_css = trim($minified_css);

    return $minified_css;
}

$data_directory_path = "Data";
$template_html_file_path = "Template.html";

if (
    is_readable($data_directory_path) &&
    is_dir($data_directory_path) &&
    is_readable($template_html_file_path) &&
    is_file($template_html_file_path)
) {
    libxml_use_internal_errors(true);
    $dom_document = new DOMDocument("1.0", "UTF-8");
    $dom_document->formatOutput = false;

    if ($dom_document->loadHTMLFile($template_html_file_path)) {
        libxml_clear_errors();

        $photographs = [];

        $directory_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $data_directory_path,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($directory_iterator as $file) {
            if (
                $file->isFile() ||
                in_array(
                    strtolower($file->getExtension()),
                    ["avif", "gif", "jpeg", "jpg", "png", "webp"],
                    true
                )
            ) {
                $relative_path = substr(
                    $file->getPathname(),
                    strlen($data_directory_path) + 1
                );

                $photographs[] = str_replace(
                    DIRECTORY_SEPARATOR,
                    "/",
                    $relative_path
                );
            } else {
                continue;
            }
        }

        shuffle($photographs);

        $head_element = $dom_document->getElementsByTagName("head")->item(0);
        if ($head_element !== null) {
            $base_element = $dom_document->createElement("base");
            $base_element->setAttribute("href", get_base_url());

            foreach ($head_element->childNodes as $child_node) {
                if (
                    $child_node->nodeName === "meta" &&
                    $child_node->hasAttribute("charset")
                ) {
                    if ($child_node->nextSibling) {
                        $head_element->insertBefore(
                            $base_element,
                            $child_node->nextSibling
                        );
                    } else {
                        $head_element->appendChild($base_element);
                    }
                    break;
                } else {
                    continue;
                }
            }
        }

        $style_elements = $dom_document->getElementsByTagName("style");
        foreach ($style_elements as $style_element) {
            $css = $style_element->nodeValue;

            if ($css !== null && $css !== "") {
                clear_dom_node($style_element);

                $style_element->appendChild(
                    $dom_document->createTextNode(minify_css($css))
                );
            } else {
                continue;
            }
        }

        $counts_p_element = $dom_document->getElementById("counts");
        if ($counts_p_element !== null) {
            clear_dom_node($counts_p_element);

            $counts_p_element->appendChild(
                $dom_document->createTextNode(
                    count($photographs) . " Photographs"
                )
            );
        }

        $article_element = $dom_document
            ->getElementsByTagName("article")
            ->item(0);
        if ($article_element !== null) {
            clear_dom_node($article_element);

            foreach ($photographs as $photograph) {
                $figure_element = $dom_document->createElement("figure");
                $image_element = $dom_document->createElement("img");

                $encoded_path = implode(
                    "/",
                    array_map("rawurlencode", explode("/", $photograph))
                );

                $image_element->setAttribute(
                    "src",
                    $data_directory_path . "/" . $encoded_path
                );
                $image_element->setAttribute("loading", "lazy");
                $image_element->setAttribute("decoding", "async");
                $image_element->setAttribute(
                    "alt",
                    pathinfo($photograph, PATHINFO_FILENAME)
                );
                $image_element->setAttribute(
                    "title",
                    pathinfo($photograph, PATHINFO_FILENAME)
                );

                $figure_element->appendChild($image_element);
                $article_element->appendChild($figure_element);
            }
        }

        $javascript_nonce = base64_encode(random_bytes(16));

        $script_elements = $dom_document->getElementsByTagName("script");
        foreach ($script_elements as $script) {
            $script->setAttribute("nonce", $javascript_nonce);
        }

        header("Referrer-Policy: no-referrer");
        header("Cross-Origin-Opener-Policy: same-origin");
        header("Cross-Origin-Resource-Policy: same-origin");
        header("X-Content-Type-Options: nosniff");
        header("Content-Type: text/html; charset=UTF-8");
        header("Cross-Origin-Embedder-Policy: require-corp");
        header(
            "Content-Security-Policy: " .
                "default-src 'self'; " .
                "base-uri 'self'; " .
                "connect-src 'self'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "script-src 'self' 'nonce-$javascript_nonce'; " .
                "manifest-src *; " .
                "worker-src 'self'; " .
                "font-src 'none'; " .
                "img-src 'self'; " .
                "media-src 'self'; " .
                "object-src 'none'; " .
                "form-action 'self'; " .
                "child-src 'none'; " .
                "frame-ancestors 'none'; " .
                "frame-src 'none'; " .
                // "require-trusted-types-for 'script'; " .
                "upgrade-insecure-requests;"
        );
        header(
            "Permissions-Policy: " .
                "accelerometer=(), " .
                "autoplay=(), " .
                "camera=(), " .
                "cross-origin-isolated=(self), " .
                "display-capture=(), " .
                "encrypted-media=(), " .
                "fullscreen=(self), " .
                "geolocation=(), " .
                "gyroscope=(), " .
                "keyboard-map=(), " .
                "magnetometer=(), " .
                "microphone=(), " .
                "midi=(), " .
                "payment=(), " .
                "picture-in-picture=(self), " .
                "publickey-credentials-get=(), " .
                "screen-wake-lock=(), " .
                "sync-xhr=(self), " .
                "usb=(), " .
                "xr-spatial-tracking=()"
        );
        header("X-Frame-Options: DENY");

        echo minify_html($dom_document->saveHTML());
    } else {
        http_response_code(500);
    }
} else {
    http_response_code(500);
}

exit();
