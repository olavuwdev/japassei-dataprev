<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use Config\Flashcards as FlashcardsConfig;
use RuntimeException;

/**
 * Obtenção e limpeza do conteúdo de uma fonte de estudo.
 *
 * A URL é sempre buscada pelo backend — a OpenAI nunca recebe apenas o link.
 * Cada requisição e cada redirecionamento passam pela validação anti-SSRF.
 */
class ContentExtractorService
{
    private FlashcardsConfig $config;

    public function __construct(?FlashcardsConfig $config = null)
    {
        $this->config = $config ?? config(FlashcardsConfig::class);
    }

    /**
     * Busca a página e devolve título + texto principal já limpos.
     *
     * @return array{title:string, text:string, hash:string, url:string}
     */
    public function fromUrl(string $url): array
    {
        $url = $this->assertSafeUrl($url);

        $html = $this->fetch($url);

        return $this->fromHtml($html, $url);
    }

    /**
     * @return array{title:string, text:string, hash:string, url:string}
     */
    public function fromHtml(string $html, string $url = ''): array
    {
        $title = '';

        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m) === 1) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $text = $this->cleanHtml($html);

        if (mb_strlen($text) < 200) {
            throw new RuntimeException('O conteúdo informado não possui informações suficientes para gerar flashcards úteis.');
        }

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : 'Conteúdo importado',
            'text'  => $text,
            'hash'  => hash('sha256', $text),
            'url'   => $url,
        ];
    }

    /**
     * Normaliza um texto colado pelo usuário.
     *
     * @return array{title:string, text:string, hash:string, url:string}
     */
    public function fromText(string $text, string $title = ''): array
    {
        $clean = $this->normalizeWhitespace(strip_tags($text));

        if (mb_strlen($clean) < 120) {
            throw new RuntimeException('O conteúdo informado não possui informações suficientes para gerar flashcards úteis.');
        }

        $clean = mb_substr($clean, 0, $this->config->maxSourceChars);

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : mb_substr($clean, 0, 80) . '…',
            'text'  => $clean,
            'hash'  => hash('sha256', $clean),
            'url'   => '',
        ];
    }

    /**
     * Remove scripts, estilos, navegação e devolve o texto principal.
     */
    public function cleanHtml(string $html): string
    {
        $clean = preg_replace('#<(script|style|noscript|svg|iframe|form|nav|header|footer|aside)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $clean = preg_replace('#<!--.*?-->#s', ' ', $clean) ?? $clean;

        // Quebras onde havia estrutura, para não colar palavras de blocos distintos.
        $clean = preg_replace('#</(p|div|li|tr|h[1-6]|section|article|br)\s*/?>#i', "\n", $clean) ?? $clean;
        $clean = preg_replace('#<br\s*/?>#i', "\n", $clean) ?? $clean;

        $clean = strip_tags($clean);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr($this->normalizeWhitespace($clean), 0, $this->config->maxSourceChars);
    }

    public function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Divide conteúdos longos em blocos, preferindo cortar em títulos ou
     * parágrafos. Nunca corta uma frase ao meio.
     *
     * @return list<string>
     */
    public function chunk(string $text, ?int $size = null): array
    {
        $size = $size ?? $this->config->chunkChars;

        if (mb_strlen($text) <= $size) {
            return [$text];
        }

        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [$text];
        $chunks     = [];
        $current    = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            // Parágrafo maior que um bloco inteiro: quebra por frases.
            if (mb_strlen($paragraph) > $size) {
                foreach ($this->splitSentences($paragraph, $size) as $piece) {
                    if (mb_strlen($current) + mb_strlen($piece) + 2 > $size && $current !== '') {
                        $chunks[]= trim($current);
                        $current = '';
                    }

                    $current .= ($current === '' ? '' : "\n\n") . $piece;
                }

                continue;
            }

            if (mb_strlen($current) + mb_strlen($paragraph) + 2 > $size && $current !== '') {
                $chunks[] = trim($current);
                $current  = '';
            }

            $current .= ($current === '' ? '' : "\n\n") . $paragraph;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function splitSentences(string $text, int $size): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $pieces    = [];
        $current   = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current) + mb_strlen($sentence) + 1 > $size && $current !== '') {
                $pieces[] = trim($current);
                $current  = '';
            }

            $current .= ($current === '' ? '' : ' ') . $sentence;
        }

        if (trim($current) !== '') {
            $pieces[] = trim($current);
        }

        return $pieces;
    }

    // ------------------------------------------------------------ Requisição

    /**
     * Busca a URL manualmente, revalidando cada redirecionamento e limitando
     * o tamanho do corpo.
     */
    private function fetch(string $url): string
    {
        $remaining = $this->config->fetchMaxRedirects;

        while (true) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => false, // validamos cada salto
                CURLOPT_TIMEOUT        => $this->config->fetchTimeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->config->fetchTimeout),
                CURLOPT_USERAGENT      => 'JaPasseiFlashcards/1.0 (+extrator de conteudo)',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,text/plain'],
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_MAXFILESIZE    => $this->config->fetchMaxBytes,
                CURLOPT_ACCEPT_ENCODING => '',
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => function ($res, $downloadSize, $downloaded) {
                    return $downloaded > $this->config->fetchMaxBytes ? 1 : 0;
                },
            ]);

            $response = curl_exec($handle);
            $status   = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $headerLn = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $type     = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
            $error    = curl_error($handle);

            curl_close($handle);

            if ($response === false) {
                throw new RuntimeException('Não conseguimos acessar o conteúdo desse link. Cole o texto diretamente ou verifique se a página é pública.'
                    . ($error !== '' ? '' : ''));
            }

            $headers = substr($response, 0, $headerLn);
            $body    = substr($response, $headerLn);

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                if ($remaining-- <= 0) {
                    throw new RuntimeException('O link possui redirecionamentos demais.');
                }

                if (preg_match('/^location:\s*(.+)$/im', $headers, $m) !== 1) {
                    throw new RuntimeException('Não conseguimos acessar o conteúdo desse link.');
                }

                // Cada destino é revalidado contra a lista de bloqueio.
                $url = $this->assertSafeUrl($this->resolveUrl(trim($m[1]), $url));
                continue;
            }

            if ($status >= 400) {
                throw new RuntimeException('Não conseguimos acessar o conteúdo desse link. Cole o texto diretamente ou verifique se a página é pública.');
            }

            if ($type !== '' && ! preg_match('#^(text/html|text/plain|application/xhtml)#i', $type)) {
                throw new RuntimeException('Esse link não retorna uma página de texto. Cole o conteúdo diretamente.');
            }

            return $body;
        }
    }

    private function resolveUrl(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts  = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $path = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $scheme . '://' . $host . $port . $path . '/' . $location;
    }

    // ---------------------------------------------------------------- SSRF

    /**
     * Valida a URL contra endereços locais, privados e metadados de nuvem.
     *
     * @throws RuntimeException
     */
    public function assertSafeUrl(string $url): string
    {
        $url   = trim($url);
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Informe uma URL válida, começando com http:// ou https://.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('Somente endereços http e https são aceitos.');
        }

        $host = strtolower($parts['host']);

        $blockedHosts = ['localhost', 'metadata.google.internal', 'metadata.goog', 'instance-data'];

        if (in_array($host, $blockedHosts, true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new RuntimeException('Endereços internos não podem ser importados.');
        }

        foreach ($this->resolveAddresses($host) as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new RuntimeException('Endereços internos ou privados não podem ser importados.');
            }
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $key => $type) {
            $records = @dns_get_record($host, $type) ?: [];

            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                if ($ip !== null) {
                    $addresses[] = $ip;
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException('Não conseguimos resolver o endereço desse link.');
        }

        return $addresses;
    }

    /**
     * Bloqueia loopback, redes privadas, link-local e metadados de nuvem
     * (169.254.169.254 é coberto pela faixa link-local).
     */
    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
