<?php
require_once __DIR__ . '/../models/Orden.php';

class OrdenController
{
    public function index(): void
    {
        if (!isset($_SESSION['usuario_id'])) { header('Location: ?page=login'); exit; }
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $modelo = new Orden();
        $busqueda = trim((string)($_GET['q'] ?? ''));
        $estadosFiltro = ['registrada','diagnostico','en_revision','en_reparacion','pendiente_repuesto','lista','entregada','cancelada'];
        $estadoSolicitado = trim((string) ($_GET['estado'] ?? ''));
        $estadoFiltro = in_array($estadoSolicitado, $estadosFiltro, true) ? $estadoSolicitado : '';
        $error = $estadoSolicitado !== '' && $estadoFiltro === '' ? 'El estado seleccionado no es válido.' : null;
        $mensaje=$_SESSION['mensaje_orden']??null; unset($_SESSION['mensaje_orden']);
        try {
            if ($_SERVER['REQUEST_METHOD']==='POST') $this->post($modelo);
            $ordenes=$modelo->listar(
                $busqueda,
                ($_SESSION['rol'] ?? '') === 'mecanico',
                (string) ($_SESSION['nombre'] ?? ''),
                (int) ($_SESSION['usuario_id'] ?? 0),
                $estadoFiltro !== '' ? $estadoFiltro : null
            );
            $vehiculos=$modelo->vehiculos(); $mecanicos=$modelo->mecanicos(); $detalle=null; $id=(int)($_GET['id']??0);
            if ($id > 0) {
                if (
                    ($_SESSION['rol'] ?? '') === 'mecanico'
                    && !$modelo->asignadaA($id, (string) ($_SESSION['nombre'] ?? ''), (int) ($_SESSION['usuario_id'] ?? 0))
                ) {
                    http_response_code(403);
                    throw new RuntimeException('La orden no está asignada a este mecánico.');
                }
                $detalle=$modelo->detalle($id);
            }
            require __DIR__.'/../views/ordenes/index.php';
        } catch(InvalidArgumentException $e) {$error=$e->getMessage();$ordenes=$modelo->listar($busqueda,($_SESSION['rol']??'')==='mecanico',(string)($_SESSION['nombre']??''),(int)($_SESSION['usuario_id']??0),$estadoFiltro!==''?$estadoFiltro:null);$vehiculos=$modelo->vehiculos();$mecanicos=$modelo->mecanicos();$id=(int)($_GET['id']??0);$detalle=$id>0?$modelo->detalle($id):null;require __DIR__.'/../views/ordenes/index.php';}
        catch(RuntimeException $e){http_response_code(403);$error=$e->getMessage();$ordenes=$modelo->listar($busqueda,($_SESSION['rol']??'')==='mecanico',(string)($_SESSION['nombre']??''),(int)($_SESSION['usuario_id']??0),$estadoFiltro!==''?$estadoFiltro:null);$vehiculos=$modelo->vehiculos();$mecanicos=$modelo->mecanicos();$detalle=null;require __DIR__.'/../views/ordenes/index.php';}
        catch(Throwable $e){http_response_code(500);$error='No se pudo completar la operación de órdenes.';$ordenes=[];$vehiculos=[];$mecanicos=[];$detalle=null;require __DIR__.'/../views/ordenes/index.php';}
    }
    private function csrf(): void { $t=$_POST['csrf_token']??''; if(!isset($_SESSION['csrf_token'])||!is_string($t)||!hash_equals($_SESSION['csrf_token'],$t)){http_response_code(403);throw new RuntimeException('Solicitud inválida');}}
    private function parsearFecha(string $valor,string $formato,string $campo): ?DateTimeImmutable {
        if($valor==='')return null;
        $fecha=DateTimeImmutable::createFromFormat('!'.$formato,$valor);
        $errores=DateTimeImmutable::getLastErrors();
        if($fecha===false||($errores!==false&&($errores['warning_count']>0||$errores['error_count']>0))||$fecha->format($formato)!==$valor)
            throw new InvalidArgumentException($campo.' no es válida. Seleccione una fecha del calendario.');
        return $fecha;
    }
    private function validarRangoFechas(?DateTimeImmutable $ingreso,?DateTimeImmutable $retorno): void {
        if($retorno!==null&&$retorno->format('Y-m-d')<($ingreso??new DateTimeImmutable())->format('Y-m-d'))
            throw new InvalidArgumentException('La fecha prevista de retorno no puede ser anterior a la fecha de ingreso.');
    }
    private function post(Orden $m): void {
        $this->csrf(); $accion=(string)($_POST['accion']??''); $rol=$_SESSION['rol']??'';
        if(!in_array($rol,['administrador','recepcionista','mecanico'],true)){http_response_code(403);throw new RuntimeException('No autorizado');}
        if($accion==='crear'){
            if(!in_array($rol,['administrador','recepcionista'],true))throw new RuntimeException('No autorizado'); $vehiculo=filter_input(INPUT_POST,'vehiculo_id',FILTER_VALIDATE_INT); if(!$vehiculo)throw new InvalidArgumentException('Seleccione un vehículo válido.');
            $mecanicosPost=$_POST['mecanico_ids']??[];
            if (!is_array($mecanicosPost)||count($mecanicosPost)===0) throw new InvalidArgumentException('Seleccione al menos un mecánico.');
            $mecanicos=[];
            foreach ($mecanicosPost as $mecanicoId) {
                $id=filter_var($mecanicoId,FILTER_VALIDATE_INT);
                if ($id&&$id>0) $mecanicos[]=(int)$id;
            }
            $mecanicos=array_values(array_unique($mecanicos));
            if (!$mecanicos) throw new InvalidArgumentException('Seleccione al menos un mecánico válido.');
            $ingreso=new DateTimeImmutable();
            $retorno=$this->parsearFecha(trim((string)($_POST['fecha_prevista_retorno']??'')),'Y-m-d','Fecha prevista de retorno');
            $this->validarRangoFechas($ingreso,$retorno);
            $datos=['vehiculo'=>$vehiculo,'usuario'=>(int)$_SESSION['usuario_id'],'ingreso'=>$ingreso->format('Y-m-d H:i:s'),'retorno'=>$retorno?$retorno->format('Y-m-d'):null,'diagnostico'=>mb_substr(trim((string)($_POST['diagnostico']??'')),0,3000,'UTF-8'),'observaciones'=>mb_substr(trim((string)($_POST['observaciones']??'')),0,3000,'UTF-8'),'mecanicos'=>$mecanicos];
            $m->crear($datos);$_SESSION['mensaje_orden']='Orden creada correctamente.';header('Location: ?page=ordenes');exit;
        }
        if($accion==='editar'){
            if($rol!== 'administrador')throw new RuntimeException('Solo el administrador puede editar órdenes.');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            $vehiculo=filter_input(INPUT_POST,'vehiculo_id',FILTER_VALIDATE_INT);
            $estado=(string)($_POST['estado']??'');
            $mecanicosPost=$_POST['mecanico_ids']??[];
            if(!$id||!$vehiculo)throw new InvalidArgumentException('Orden o vehículo inválido.');
            if(!is_array($mecanicosPost)||count($mecanicosPost)===0)throw new InvalidArgumentException('Seleccione al menos un mecánico.');
            $mecanicos=[];
            foreach($mecanicosPost as $mecanicoId){$mid=filter_var($mecanicoId,FILTER_VALIDATE_INT);if($mid&&(int)$mid>0)$mecanicos[]=(int)$mid;}
            if(!$mecanicos)throw new InvalidArgumentException('Seleccione al menos un mecánico válido.');
            $retorno=$this->parsearFecha(trim((string)($_POST['fecha_prevista_retorno']??'')),'Y-m-d','Fecha prevista de retorno');
            $ordenActual=$m->detalle((int)$id);
            if(!$ordenActual)throw new InvalidArgumentException('Orden no encontrada.');
            $ingresoActual=new DateTimeImmutable((string)$ordenActual['orden']['fecha_ingreso']);
            $this->validarRangoFechas($ingresoActual,$retorno);
            $costo=trim((string)($_POST['costo_final']??''));
            $costoFinal=($costo!==''?filter_var($costo,FILTER_VALIDATE_FLOAT):null);
            if($costo!==''&&($costoFinal===false||$costoFinal<0))throw new InvalidArgumentException('El costo final no es válido.');
            $permitidos=['registrada','diagnostico','en_revision','en_reparacion','pendiente_repuesto','lista','listo','entregada','cancelada'];
            if(!in_array($estado,$permitidos,true))throw new InvalidArgumentException('Estado inválido.');
            $m->editar((int)$id,(int)$vehiculo,$retorno?$retorno->format('Y-m-d'):null,$estado,mb_substr(trim((string)($_POST['diagnostico']??'')),0,3000,'UTF-8'),mb_substr(trim((string)($_POST['observaciones']??'')),0,3000,'UTF-8'),$costoFinal!==false?$costoFinal:null,$mecanicos,(int)$_SESSION['usuario_id']);
            $_SESSION['mensaje_orden']='Orden editada correctamente.';
            header('Location: ?page=ordenes&id='.$id);exit;
        } elseif($accion==='eliminar'){
                if(!in_array($rol,['administrador','recepcionista'],true))throw new RuntimeException('No tiene permisos para eliminar órdenes.');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            if(!$id)throw new InvalidArgumentException('Orden inválida.');
            $m->eliminar((int)$id,(int)$_SESSION['usuario_id']);
            $_SESSION['mensaje_orden']='Orden eliminada correctamente.';
            header('Location: ?page=ordenes');exit;
        }
        if($accion==='estado'){
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$estado=(string)($_POST['estado']??'');
            $permitidos=in_array($rol,['administrador','recepcionista'],true)
                ? ['registrada','diagnostico','en_revision','en_reparacion','pendiente_repuesto','lista','listo','entregada','cancelada']
                : ['en_revision','lista'];
            if(!$id||!in_array($estado,$permitidos,true))throw new InvalidArgumentException('No tiene permiso para cambiar a este estado.');
            if($rol==='mecanico'&&!$m->asignadaA((int)$id,(string)($_SESSION['nombre']??''),(int)$_SESSION['usuario_id']) )throw new RuntimeException('La orden no está asignada a este mecánico.');
            if($rol==='mecanico'&&$estado==='lista'){
                $resumen=$m->resumenTareas((int)$id);
                if((int)$resumen['total']===0)throw new InvalidArgumentException('Agregue los trabajos realizados antes de avisar a recepción.');
                if((int)$resumen['pendientes']>0)throw new InvalidArgumentException('Marque todos los trabajos como realizados antes de terminar la orden.');
                if((float)$resumen['monto']<=0)throw new InvalidArgumentException('La orden no tiene un monto calculado para enviar a recepción.');
            }
            $m->estado($id,(int)$_SESSION['usuario_id'],$estado,trim((string)($_POST['observaciones']??'')));
            $_SESSION['mensaje_orden']=$rol==='mecanico'&&$estado==='lista'
                ? 'Orden marcada como terminada. Recepción ya puede coordinar la entrega.'
                : 'Estado actualizado.';
        } elseif($accion==='actualizar_tecnico'){
            if($rol!== 'mecanico')throw new RuntimeException('Solo el mecánico asignado puede realizar esta actualización.');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            if(!$id)throw new InvalidArgumentException('Orden inválida.');
            if(!$m->asignadaA((int)$id,(string)($_SESSION['nombre']??''),(int)$_SESSION['usuario_id']))throw new RuntimeException('La orden no está asignada a este mecánico.');
            $diagnostico=mb_substr(trim((string)($_POST['diagnostico']??'')),0,3000,'UTF-8');
            $observaciones=mb_substr(trim((string)($_POST['observaciones']??'')),0,3000,'UTF-8');
            $retorno=$this->parsearFecha(trim((string)($_POST['fecha_prevista_retorno']??'')),'Y-m-d','Fecha prevista de retorno');
            $detalle=$m->detalle((int)$id);
            $ingreso=new DateTimeImmutable((string)$detalle['orden']['fecha_ingreso']);
            $this->validarRangoFechas($ingreso,$retorno);
            $m->actualizarTecnico((int)$id,$diagnostico,$observaciones,$retorno?$retorno->format('Y-m-d'):null,(int)$_SESSION['usuario_id']);
            $_SESSION['mensaje_orden']='Información técnica actualizada y enviada a administración y recepción.';
        } elseif($accion==='tareas_catalogo'){
            if(!in_array($rol,['administrador','mecanico'],true))throw new RuntimeException('No autorizado');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            $seleccionadas=$_POST['tareas_catalogo']??[];
            if(!$id||!is_array($seleccionadas)||!$seleccionadas)throw new InvalidArgumentException('Seleccione al menos un trabajo.');
            if($rol==='mecanico'&&!$m->asignadaA((int)$id,(string)($_SESSION['nombre']??''),(int)$_SESSION['usuario_id']))throw new RuntimeException('La orden no está asignada a este mecánico.');
            $catalogo=[];
            foreach(Orden::catalogoTareas() as $tareas) $catalogo=array_merge($catalogo,$tareas);
            foreach($seleccionadas as $tarea){
                if(!is_string($tarea)||!in_array($tarea,$catalogo,true))throw new InvalidArgumentException('La selección contiene un trabajo inválido.');
            }
            $agregadas=$m->agregarTareasCatalogo((int)$id,$seleccionadas);
            $_SESSION['mensaje_orden']=$agregadas>0
                ? 'Se agregaron '.$agregadas.' trabajo'.($agregadas===1?'':'s').' pendiente'.($agregadas===1?'':'s').' a la orden.'
                : 'Los trabajos seleccionados ya estaban registrados en esta orden.';
        } elseif($accion==='completar_tareas'){
            if(!in_array($rol,['administrador','mecanico'],true))throw new RuntimeException('No autorizado');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            $tareas=$_POST['tareas_completadas']??[];
            if(!$id||!is_array($tareas)||!$tareas)throw new InvalidArgumentException('Seleccione al menos un trabajo realizado.');
            foreach($tareas as $tareaId){
                if(filter_var($tareaId,FILTER_VALIDATE_INT)===false||(int)$tareaId<1)throw new InvalidArgumentException('La selección de trabajos no es válida.');
            }
            if($rol==='mecanico'&&!$m->asignadaA((int)$id,(string)($_SESSION['nombre']??''),(int)$_SESSION['usuario_id']))throw new RuntimeException('La orden no está asignada a este mecánico.');
            $completadas=$m->completarTareas((int)$id,$tareas);
            $_SESSION['mensaje_orden']=$completadas>0
                ? 'Se marcaron '.$completadas.' trabajo'.($completadas===1?'':'s').' como completado'.($completadas===1?'':'s').'.'
                : 'Los trabajos seleccionados ya estaban completados.';
        } elseif($accion==='precio'){
            if(!in_array($rol,['administrador','recepcionista'],true))throw new RuntimeException('No autorizado para registrar precios.');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
            $precio=filter_var($_POST['costo_final']??'',FILTER_VALIDATE_FLOAT);
            if(!$id||$precio===false||$precio<0)throw new InvalidArgumentException('Ingrese un precio válido, igual o mayor que cero.');
            $m->actualizarPrecio((int)$id,(float)$precio);
            $_SESSION['mensaje_orden']='Precio de la orden guardado correctamente.';
        } elseif($accion==='tarea'){
            if(!in_array($rol,['administrador','mecanico'],true))throw new RuntimeException('No autorizado');
            $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$descripcion=trim((string)($_POST['descripcion']??''));$estado=(string)($_POST['estado_tarea']??'pendiente');if(!$id||$descripcion===''||!in_array($estado,['pendiente','en_proceso','completada'],true))throw new InvalidArgumentException('Datos de tarea incompletos.');
            if($rol==='mecanico'&&!$m->asignadaA((int)$id,(string)($_SESSION['nombre']??''),(int)$_SESSION['usuario_id']))throw new RuntimeException('La orden no está asignada a este mecánico.');
            $costo=$rol==='mecanico'?null:(isset($_POST['costo_tarea'])&&$_POST['costo_tarea']!==''?filter_var($_POST['costo_tarea'],FILTER_VALIDATE_FLOAT):null);$m->tarea($id,$descripcion,$estado,$costo);$_SESSION['mensaje_orden']='Tarea registrada.';
        } elseif($accion==='pago'){
            if(!in_array($rol,['administrador','recepcionista'],true))throw new RuntimeException('No autorizado');$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$monto=filter_var($_POST['monto']??'',FILTER_VALIDATE_FLOAT);$metodo=(string)($_POST['metodo']??'efectivo');if(!$id||$monto===false||$monto<=0||!in_array($metodo,['efectivo','transferencia','otro'],true))throw new InvalidArgumentException('Datos de pago inválidos.');$m->pago($id,(int)$_SESSION['usuario_id'],$monto,$metodo,trim((string)($_POST['referencia']??'')),trim((string)($_POST['observaciones']??'')));$_SESSION['mensaje_orden']='Pago registrado.';
        } else throw new InvalidArgumentException('Acción inválida.');
        header('Location: ?page=ordenes&id='.$_POST['id']);exit;
    }
}
