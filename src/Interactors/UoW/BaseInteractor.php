<?php

namespace Xofttion\Project\Interactors\UoW;

use Exception;
use Closure;
use Illuminate\Database\QueryException;

use Xofttion\Project\Utils\HttpCode;
use Xofttion\Project\Response;
use Xofttion\Project\Exceptions\ProjectException;

class BaseInteractor {
    use \Xofttion\Project\Traits\AuthenticatedTrait;
    use \Xofttion\Project\Traits\ResponseTrait;
    use \Xofttion\Project\SOA\Traits\UnitOfWorkTrait;
    use \Xofttion\Project\SOA\Traits\EntityMapperTrait;
    
    // Constructor de la clase BaseInteractor
    
    public function __construct() {
        
    }
    
    // Métodos de la clase BaseInteractor

    /**
     * 
     * @param Closure $callback
     * @return Response
     */
    protected function controllerException(Closure $callback): Response {
        try {
            return $callback();
        } catch (Exception $exception) {
            return $this->responseException($exception);
        }
    }

    /**
     * 
     * @param Closure $callback
     * @return Response
     */
    protected function controllerTransaction(Closure $callback): Response {
        if (is_null($this->getUnitOfWork()->getMapper())) {
            $this->getUnitOfWork()->setMapper($this->getEntityMapper());
        }
        
        $this->getUnitOfWork()->transaction(); // Iniciando
        
        try {
            $response = $callback(); // Ejecutando procesos
            
            $this->getUnitOfWork()->commit(); // Confirmando
            
            return $response; // Retornando resultado del proceso 
        } catch (Exception $exception) {
            $this->getUnitOfWork()->rollback(); // Anulando
            
            return $this->responseException($exception); // Excepción
        }
    }
    
    /**
     * 
     * @param Exception $exception
     * @return Response
     */
    private function responseException(Exception $exception): Response {
        if ($exception instanceof ProjectException) {
            return new Response(false, $exception->getMessage(), $exception->getHttpCode(), $exception->getAttributes());
        } // La excepción fue generada por lógica de la aplicación

        $data = $this->getExceptionData($exception); // Excepción

        if ($exception instanceof QueryException) {
            $data["sql"] = [
                "code" => $exception->errorInfo[1], 
                "info" => $exception->errorInfo
            ];
        } // Se generó una excepción respecto a la base de datos

        return new Response(false, $exception->getMessage(), HttpCode::INTERNAL_SERVER_ERROR, $data);
    }

    /**
     * 
     * @param Exception $exception
     * @return array
     */
    private function getExceptionData(Exception $exception): array {
        return [
            "message"   => $exception->getMessage(),
            "class"     => get_class($exception),
            "exception" => [
                "errorCode" => $exception->getCode(),
                "file"      => $exception->getFile(),
                "line"      => $exception->getLine(),
                "trace"     => $exception->getTrace()
            ]
        ];
    }
}