<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\UpdateProduct\UpdateProductCommand;
use OrderHub\Application\Query\ListProducts\ListProductsQuery;
use OrderHub\Domain\Shared\Exceptions\DomainException;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

/**
 * Traditional form-driven CRUD (POST + redirect, no HTMX needed here per the
 * spec) that reuses the exact same commands/queries as the JSON API — this
 * controller contains no business rule, only orchestration and rendering.
 */
final class ProductController
{
    use CurrentUser;
    use RendersTemplates;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly Session $session,
        private readonly Environment $twig,
    ) {
    }

    public function list(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $products = $this->queryBus->ask(new ListProductsQuery($tenantId));

        return $this->render($this->twig, $this->session, 'products/list.html.twig', ['products' => $products]);
    }

    public function newForm(WebRequest $request): WebResponse
    {
        return $this->render($this->twig, $this->session, 'products/form.html.twig', ['product' => null, 'errors' => []]);
    }

    public function create(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $name = trim((string) $request->input('name', ''));
        $priceCents = $this->parsePriceToCents((string) $request->input('price', ''));
        $stockQuantity = max(0, (int) $request->input('stockQuantity', '0'));

        try {
            $this->commandBus->dispatch(new CreateProductCommand($tenantId, $name, $priceCents, $stockQuantity));
        } catch (DomainException $e) {
            return $this->render($this->twig, $this->session, 'products/form.html.twig', [
                'product' => null,
                'errors' => [$e->getMessage()],
            ], 422);
        }

        $this->session->flash('success', 'Produto criado com sucesso.');

        return WebResponse::redirect('/app/products');
    }

    public function editForm(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $product = $this->findProduct($tenantId, $id);
        if ($product === null) {
            return $this->render($this->twig, $this->session, 'errors/generic.html.twig', [
                'status' => 404,
                'title' => 'Não encontrado',
                'message' => 'Produto não encontrado.',
            ], 404);
        }

        return $this->render($this->twig, $this->session, 'products/form.html.twig', ['product' => $product, 'errors' => []]);
    }

    public function update(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $name = trim((string) $request->input('name', ''));
        $priceCents = $this->parsePriceToCents((string) $request->input('price', ''));
        $stockQuantity = max(0, (int) $request->input('stockQuantity', '0'));

        try {
            $this->commandBus->dispatch(new UpdateProductCommand($tenantId, $id, $name !== '' ? $name : null, $priceCents, $stockQuantity));
        } catch (DomainException $e) {
            return $this->render($this->twig, $this->session, 'products/form.html.twig', [
                'product' => $this->findProduct($tenantId, $id),
                'errors' => [$e->getMessage()],
            ], 422);
        }

        $this->session->flash('success', 'Produto atualizado com sucesso.');

        return WebResponse::redirect('/app/products');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProduct(string $tenantId, string $id): ?array
    {
        $products = $this->queryBus->ask(new ListProductsQuery($tenantId));
        foreach ($products as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    private function parsePriceToCents(string $decimal): int
    {
        $normalized = str_replace(',', '.', trim($decimal));

        return (int) round(((float) $normalized) * 100);
    }
}
