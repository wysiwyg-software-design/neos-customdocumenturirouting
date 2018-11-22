<?php
namespace Flownative\Neos\CustomDocumentUriRouting\Routing;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Mvc\Routing\RoutingComponent;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Routing\FrontendNodeRoutePartHandlerInterface;
use Neos\Neos\TypeConverter\NodeConverter;

/**
 *
 */
class RedirectToFullNodeUrlComponent implements ComponentInterface
{
    /**
     * @Flow\Inject
     * @var NodeConverter
     */
    protected $nodeConverter;

    /**
     * @Flow\InjectConfiguration(path="mixinNodeTypeName", package="Flownative.Neos.CustomDocumentUriRouting")
     * @var string
     */
    protected $mixinNodeTypeName;

    /**
     * @Flow\InjectConfiguration(path="uriPathPropertyName", package="Flownative.Neos.CustomDocumentUriRouting")
     * @var string
     */
    protected $uriPathPropertyName;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param ComponentContext $componentContext
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function handle(ComponentContext $componentContext)
    {
        $matchResults = $componentContext->getParameter(RoutingComponent::class, 'matchResults');
        if (!isset($matchResults['node'])) {
            return;
        }

        $requestedUriPath = $componentContext->getHttpRequest()->getUri()->getPath();

        if (strpos($requestedUriPath, '.html') === false) {
            return;
        }

        /** @var NodeInterface $node */
        $node = $this->nodeConverter->convertFrom($matchResults['node'], NodeInterface::class);

        if (!$node->getNodeType()->isOfType($this->mixinNodeTypeName)) {
            return;
        }

        $fullUriPath = $node->getProperty($this->uriPathPropertyName);
        if (empty($fullUriPath)) {
            return;
        }

        $requestedUriPath = str_replace('.html', '', $requestedUriPath);
        $requestedUriPathParts = explode('@', $requestedUriPath);
        $requestedUriPathWithoutContext = $requestedUriPathParts[0];
        $requestedUriPathWithoutContext = trim($requestedUriPathWithoutContext, '/');

        if ($requestedUriPathWithoutContext === $fullUriPath) {
            return;
        }

        if (strstr($requestedUriPathWithoutContext, '/') === '/' . $fullUriPath) {
            return;
        }

        $nodeRoutePartHandler = $this->objectManager->get(FrontendNodeRoutePartHandlerInterface::class);
        $nodeRoutePartHandler->setName('node');

        $routeValues = ['node' => $node];
        $result = $nodeRoutePartHandler->resolve($routeValues);
        if ($result === false) {
            die('no resolve');
            return;
        }

        $routePart = $nodeRoutePartHandler->getValue();
        // TODO: Hardcoded uri suffix.
        $shortUrlPath = '/' . ltrim($routePart, '/') . '.html';

        $response = $componentContext->getHttpResponse();
        $response = $response->withStatus(301)->withAddedHeader('Location', $shortUrlPath);
        $componentContext->replaceHttpResponse($response);
    }
}
