<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    protected \CodeIgniter\Session\Session $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        $this->session = service('session');
    }

    /**
     * ID do usuário autenticado (0 quando não logado — rotas protegidas
     * garantem via AuthFilter que haverá usuário em sessão).
     */
    protected function userId(): int
    {
        return (int) ($this->session->get('user')['id'] ?? 0);
    }

    /**
     * Resposta JSON padronizada para a API interna.
     */
    protected function jsonResponse(bool $ok, array $data = [], string $message = '', int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => $ok,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}
