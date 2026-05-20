<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\CI4ApiCrudMaker\Config\BaseScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;

class Scaffolding extends BaseScaffoldingConfig
{
    public function build(): ScaffoldingConfig
    {
        $defaults = ScaffoldingConfig::defaults();

        return new ScaffoldingConfig(
            controllerBaseClass: $defaults->controllerBaseClass,
            serviceBaseClass: $defaults->serviceBaseClass,
            serviceContractInterface: $defaults->serviceContractInterface,
            modelBaseClass: $defaults->modelBaseClass,
            entityBaseClass: $defaults->entityBaseClass,
            migrationBaseClass: $defaults->migrationBaseClass,
            requestDtoBaseClass: $defaults->requestDtoBaseClass,
            responseDtoInterface: $defaults->responseDtoInterface,
            repositoryInterface: $defaults->repositoryInterface,
            responseMapperInterface: $defaults->responseMapperInterface,
            repositoryImplementation: $defaults->repositoryImplementation,
            responseMapperImplementation: $defaults->responseMapperImplementation,
            servicesFactoryClass: $defaults->servicesFactoryClass,
            paths: $defaults->paths,
            // Override: domain apps delegate auth to the hub via DomainAuthFilter,
            // and gate by domain-local permission codes (`items.read` etc.).
            protectedRouteFilters: ['domainauth', 'permission:items.read', 'throttle'],
            appNamespace: $defaults->appNamespace,
            openApiTagPrefix: $defaults->openApiTagPrefix,
            conditionalControllerTraits: $defaults->conditionalControllerTraits,
        );
    }
}
