<?php

namespace Example\Api\Controllers;

use Luminus\Request;
use Luminus\Response;

class ProductController
{
    private array $products = [
        ['id' => 1, 'name' => 'Laptop', 'price' => 999.99],
        ['id' => 2, 'name' => 'Mouse', 'price' => 29.99],
        ['id' => 3, 'name' => 'Keyboard', 'price' => 79.99],
    ];

    private int $nextId = 4;

    public function index(Response $res): Response
    {
        return $res->json([
            'data' => array_values($this->products),
            'count' => count($this->products),
        ]);
    }

    public function show(Response $res, string $id): Response
    {
        $product = $this->find((int) $id);

        if (!$product) {
            return $res->json(['error' => 'Product not found'], 404);
        }

        return $res->json(['data' => $product]);
    }

    public function store(Request $req, Response $res): Response
    {
        $data = $req->json();

        if (empty($data['name']) || !isset($data['price'])) {
            return $res->json(['error' => 'name and price are required'], 422);
        }

        $product = [
            'id' => $this->nextId++,
            'name' => $data['name'],
            'price' => (float) $data['price'],
        ];

        $this->products[] = $product;

        return $res->json(['data' => $product, 'message' => 'Created'], 201);
    }

    public function update(Request $req, Response $res, string $id): Response
    {
        $index = $this->findIndex((int) $id);

        if ($index === null) {
            return $res->json(['error' => 'Product not found'], 404);
        }

        $data = $req->json();

        if (isset($data['name'])) {
            $this->products[$index]['name'] = $data['name'];
        }
        if (isset($data['price'])) {
            $this->products[$index]['price'] = (float) $data['price'];
        }

        return $res->json([
            'data' => $this->products[$index],
            'message' => 'Updated',
        ]);
    }

    public function destroy(Response $res, string $id): Response
    {
        $index = $this->findIndex((int) $id);

        if ($index === null) {
            return $res->json(['error' => 'Product not found'], 404);
        }

        array_splice($this->products, $index, 1);

        return $res->json(['message' => 'Deleted']);
    }

    private function find(int $id): ?array
    {
        foreach ($this->products as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }

    private function findIndex(int $id): ?int
    {
        foreach ($this->products as $i => $p) {
            if ($p['id'] === $id) return $i;
        }
        return null;
    }
}
