<?php
/**
 * File containing the RequestEventListener class.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace BD\Bundle\XmlRpcBundle\EventListener;

use BD\Bundle\XmlRpcBundle\XmlRpc\RequestGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class RequestEventListener implements EventSubscriberInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\HttpKernel
     */
    private $httpKernel;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestGenerator
     */
    private $requestGenerator;

    public function __construct( HttpKernel $kernel, RouterInterface $router, RequestGenerator $requestGenerator )
    {
        $this->httpKernel = $kernel;
        $this->router = $router;
        $this->requestGenerator = $requestGenerator;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(
                array( 'onKernelRequest', 16 ),
            )
        );
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onKernelRequest( GetResponseEvent $event )
    {
        if ( $event->getRequestType() != HttpKernelInterface::MASTER_REQUEST )
            return;

        if ( strpos( $event->getRequest()->getPathInfo(), '/xmlrpc2' ) !== 0 || $event->getRequest()->getMethod() !== 'POST' )
            return;

        try
        {
            $request = $this->requestGenerator->generateFromRequest( $event->getRequest() );
        }
        catch ( \UnexpectedValueException $e )
        {
            $event->setResponse( new Response( "Invalid request XML\n" . $e->getMessage(), 400 ) );
            return;
        }

        $requestContext = new RequestContext();
        $requestContext->fromRequest( $request );

        $originalContext = $this->router->getContext();
        $this->router->setContext( $requestContext );

        $response = $this->httpKernel->handle( $request );

        $event->setResponse( $response );
        $this->router->setContext( $originalContext );

        if ( $response instanceof Response )
            $event->setResponse( $response );
    }
}
