# jponchiroli/laravel-base

[![Tests](https://github.com/JPonchiroli/laravel-base/actions/workflows/tests.yml/badge.svg)](https://github.com/JPonchiroli/laravel-base/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/jponchiroli/laravel-base.svg)](https://packagist.org/packages/jponchiroli/laravel-base)
[![Total Downloads](https://img.shields.io/packagist/dt/jponchiroli/laravel-base.svg)](https://packagist.org/packages/jponchiroli/laravel-base)
[![License](https://img.shields.io/packagist/l/jponchiroli/laravel-base.svg)](LICENSE)

> 🇬🇧 [English version](README.md)

## O que é

Uma base para APIs em Laravel 13. Traz classes base em camadas (service, repository, DTOs, requests, resources), respostas e erros JSON padronizados com mensagens traduzidas (`en`, `pt_BR`) e o comando `make:domain`, que gera toda a camada de API de uma entidade.

## Como funciona

1. Você instala o pacote e roda um comando de setup.
2. `php artisan make:domain Produto` gera o controller, service, repository, DTOs, requests e rotas da entidade. Cada classe gerada é uma subclasse vazia de uma classe base que vive no pacote.
3. Você só adiciona a migration, o `$fillable` do model e as regras de validação dos Requests.
4. Os endpoints respondem com um envelope fixo (`{ "message": "...", "data": ... }`), os erros seguem um único formato e as mensagens de validação são derivadas das regras e traduzidas.

O código gerado pertence ao seu projeto, então você pode sobrescrever qualquer comportamento nas classes da própria entidade.

## Instalação

Requer PHP 8.3+ e Laravel 13.

```bash
composer require jponchiroli/laravel-base
php artisan laravel-base:install
```

O service provider é descoberto automaticamente. O Composer não executa comandos do Artisan para dependências, por isso o `laravel-base:install` é um passo manual; ele pode ser rodado mais de uma vez sem problema. Opções, chaves de configuração e tags de publicação estão em [docs/install-and-configuration.md](docs/install-and-configuration.md).

## Documentação

A documentação técnica (em inglês) fica em [docs/](docs/README.md):

| Tópico | |
|---|---|
| [Primeiros passos](docs/getting-started.md) | Da instalação a um CRUD funcionando |
| [Arquitetura](docs/architecture.md) | Como o pacote é organizado |
| [Ciclo da requisição](docs/request-lifecycle.md) | O que acontece com uma requisição |
| [Camadas](docs/layers.md) | Responsabilidade de cada camada e classe base |
| [DTOs](docs/dtos.md) | Como os dados validados trafegam |
| [Respostas e erros](docs/responses-and-errors.md) | Formatos de resposta e o handler de erros |
| [Validação e traduções](docs/validation-and-i18n.md) | Mensagens derivadas e idiomas |
| [`make:domain`](docs/make-domain.md) | O gerador, os stubs e as rotas |
| [Testes](docs/testing.md) | Como o pacote é testado |

## Usando com agentes de IA

O pacote traz um guia para agentes de IA de programação. Ele ensina o agente a rodar o `make:domain` em vez de escrever cada arquivo à mão, o que economiza tokens e mantém o código consistente. Publique-o no seu projeto como `AGENTS.md`:

```bash
php artisan vendor:publish --tag=laravel-base-agents
```

Detalhes em [docs/ai-agent-guide.md](docs/ai-agent-guide.md).

## Publicando uma versão

O `make:domain` também gera a documentação Swagger/OpenAPI de cada domínio (`app/Docs/`, attributes do swagger-php), a partir das regras dos Requests e das colunas da tabela, servida em `/docs`. Atualize com `php artisan domain:docs`.

## Licença

MIT. Veja [LICENSE](LICENSE).
