<?php

namespace Luminus;

class Controller
{
    public function __construct(
        protected Request $request,
        protected View $view,
    ) {
    }

    /**
     * Render a template as a Response.
     * HTMX requests (HX-Request: true) receive a partial without layout;
     * normal requests receive the full rendered view.
     */
    protected function render(string $template, array $data = []): Response
    {
        $html = $this->request->header('HX-Request') === 'true'
            ? $this->view->partial($template, $data)
            : $this->view->render($template, $data);

        return (new Response())->body($html);
    }

    /**
     * Tell HTMX to perform a client-side redirect (HTTP 200 + HX-Redirect).
     */
    protected function htmxRedirect(string $url): Response
    {
        return (new Response())
            ->status(200)
            ->header('HX-Redirect', $url);
    }

    /**
     * Tell HTMX to perform a client-side redirect, ensuring the URL is safe to prevent Open Redirect.
     */
    protected function safeHtmxRedirect(string $url, string $default = '/'): Response
    {
        $response = new Response();
        $safeUrl = $response->isSafeUrl($url) ? $url : $default;

        return $response
            ->status(200)
            ->header('HX-Redirect', $safeUrl);
    }

    /**
     * Append an out-of-band HTMX flash message to the response body.
     */
    protected function withFlash(Response $response, string $msg): Response
    {
        $safe = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flash = '<div id="flash" hx-swap-oob="true">' . $safe . '</div>';

        return $response->body((string) $response . $flash);
    }
}
