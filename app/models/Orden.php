<?php
require_once __DIR__ . '/../../config/database.php';

class Orden
{
    private PDO $conexion;
    public function __construct(){
        $this->conexion=(new Database())->connect();
        $this->asegurarTablaTarifas();
    }
    private function asegurarTablaTarifas(): void {
        $this->conexion->exec(
            'CREATE TABLE IF NOT EXISTS tarifas_trabajos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                descripcion VARCHAR(255) NOT NULL UNIQUE,
                precio DECIMAL(10,2) UNSIGNED NOT NULL,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $predeterminadas=self::preciosReferencialesTareas();
        $marcadores=implode(',',array_fill(0,count($predeterminadas),'(?, ?)'));
        $valores=[];
        foreach($predeterminadas as $descripcion=>$precio){
            $valores[]=$descripcion;
            $valores[]=$precio;
        }
        $sembrar=$this->conexion->prepare(
            'INSERT IGNORE INTO tarifas_trabajos (descripcion, precio) VALUES '.$marcadores
        );
        $sembrar->execute($valores);
    }
    public static function catalogoTareas(): array {
        return [
            'Mantenimiento general' => [
                'Inspección general del vehículo y niveles',
                'Cambio de aceite del motor',
                'Cambio de filtro de aceite',
                'Cambio de filtro de aire del motor',
                'Cambio de filtro de combustible',
            ],
            'Motor' => [
                'Diagnóstico de motor',
                'Cambio de bujías',
                'Cambio de bobinas de encendido',
                'Cambio de correa auxiliar',
                'Cambio de kit de distribución según fabricante',
                'Cambio de retenes por fuga de aceite',
                'Calibración del reglaje de válvulas',
                'Cambio de empaque de culata',
                'Reparación general del motor',
            ],
            'Frenos' => [
                'Inspección del sistema de frenos',
                'Mantenimiento y limpieza del sistema de frenos',
                'Cambio de pastillas de freno',
                'Cambio de discos o tambores de freno',
                'Cambio de líquido de frenos',
                'Ajuste del freno de estacionamiento',
            ],
            'Refrigeración' => [
                'Cambio de refrigerante',
                'Reparación de fuga del sistema de refrigeración',
                'Cambio de radiador o mangueras',
                'Cambio de termostato o bomba de agua',
                'Reparación del ventilador de refrigeración',
            ],
            'Transmisión y embrague' => [
                'Inspección del sistema de embrague',
                'Cambio de disco de embrague dañado',
                'Cambio de kit de embrague',
                'Cambio de aceite de transmisión',
                'Cambio de semieje o junta homocinética',
            ],
            'Suspensión, dirección y ruedas' => [
                'Inspección de suspensión y amortiguadores',
                'Cambio de amortiguadores',
                'Cambio de rótulas o terminales de dirección',
                'Alineación de ruedas',
                'Balanceo de ruedas',
            ],
            'Sistema eléctrico' => [
                'Prueba de batería',
                'Reparación del alternador y sistema de carga',
                'Reparación del motor de arranque',
                'Reparación de luces o sistema eléctrico',
            ],
            'Escape' => [
                'Inspección del sistema de escape',
            ],
        ];
    }
    public static function preciosReferencialesTareas(): array {
        return [
            'Inspección general del vehículo y niveles'=>40,
            'Cambio de aceite del motor'=>60,
            'Cambio de filtro de aceite'=>20,
            'Cambio de filtro de aire del motor'=>20,
            'Cambio de filtro de combustible'=>40,
            'Diagnóstico de motor'=>80,
            'Cambio de bujías'=>80,
            'Cambio de bobinas de encendido'=>80,
            'Cambio de correa auxiliar'=>100,
            'Cambio de kit de distribución según fabricante'=>350,
            'Cambio de retenes por fuga de aceite'=>180,
            'Calibración del reglaje de válvulas'=>220,
            'Cambio de empaque de culata'=>800,
            'Reparación general del motor'=>1500,
            'Inspección del sistema de frenos'=>40,
            'Mantenimiento y limpieza del sistema de frenos'=>80,
            'Cambio de pastillas de freno'=>80,
            'Cambio de discos o tambores de freno'=>120,
            'Cambio de líquido de frenos'=>80,
            'Ajuste del freno de estacionamiento'=>50,
            'Cambio de refrigerante'=>60,
            'Reparación de fuga del sistema de refrigeración'=>120,
            'Cambio de radiador o mangueras'=>150,
            'Cambio de termostato o bomba de agua'=>180,
            'Reparación del ventilador de refrigeración'=>120,
            'Inspección del sistema de embrague'=>50,
            'Cambio de disco de embrague dañado'=>450,
            'Cambio de kit de embrague'=>500,
            'Cambio de aceite de transmisión'=>80,
            'Cambio de semieje o junta homocinética'=>180,
            'Inspección de suspensión y amortiguadores'=>50,
            'Cambio de amortiguadores'=>160,
            'Cambio de rótulas o terminales de dirección'=>120,
            'Alineación de ruedas'=>80,
            'Balanceo de ruedas'=>60,
            'Prueba de batería'=>30,
            'Reparación del alternador y sistema de carga'=>180,
            'Reparación del motor de arranque'=>150,
            'Reparación de luces o sistema eléctrico'=>100,
            'Inspección del sistema de escape'=>40,
        ];
    }
    public function precioReferencial(string $tarea): float {
        if(!array_key_exists($tarea,self::preciosReferencialesTareas()))throw new InvalidArgumentException('La tarea no tiene una tarifa referencial configurada.');
        $stmt=$this->conexion->prepare('SELECT precio FROM tarifas_trabajos WHERE descripcion=:descripcion');
        $stmt->execute(['descripcion'=>$tarea]);
        $precio=$stmt->fetchColumn();
        if($precio===false)throw new InvalidArgumentException('La tarea no tiene una tarifa referencial configurada.');
        return (float)$precio;
    }
    public function tarifasTareas(): array {
        $stmt=$this->conexion->query('SELECT descripcion, precio FROM tarifas_trabajos ORDER BY descripcion');
        $tarifas=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila){
            $tarifas[(string)$fila['descripcion']]=(float)$fila['precio'];
        }
        return $tarifas;
    }
    public function guardarTarifasTareas(array $tarifas): void {
        $catalogo=self::preciosReferencialesTareas();
        $this->conexion->beginTransaction();
        try {
            $actualizar=$this->conexion->prepare(
                'UPDATE tarifas_trabajos SET precio=:precio WHERE descripcion=:descripcion'
            );
            foreach($tarifas as $descripcion=>$precio){
                if(!is_string($descripcion)||!array_key_exists($descripcion,$catalogo)||!is_numeric($precio)||(float)$precio<0){
                    throw new InvalidArgumentException('Una de las tarifas enviadas no es válida.');
                }
                $actualizar->execute(['precio'=>(float)$precio,'descripcion'=>$descripcion]);
                if($actualizar->rowCount()===0){
                    $verificar=$this->conexion->prepare('SELECT COUNT(*) FROM tarifas_trabajos WHERE descripcion=:descripcion');
                    $verificar->execute(['descripcion'=>$descripcion]);
                    if((int)$verificar->fetchColumn()===0)throw new InvalidArgumentException('No se encontró una tarifa del catálogo.');
                }
            }
            $this->conexion->commit();
        } catch(Throwable $e){
            if($this->conexion->inTransaction())$this->conexion->rollBack();
            throw $e;
        }
    }
    public function mecanicos(): array {
        $s=$this->conexion->query('SELECT id,nombre FROM mecanicos WHERE activo=1 ORDER BY nombre');
        return $s->fetchAll();
    }
    public function listar(string $busqueda='', bool $soloAsignadas=false, string $mecanico='', int $usuarioId=0, ?string $estado=null): array {
        $sql='SELECT DISTINCT o.*,v.placa,v.marca,v.modelo,c.nombre AS cliente FROM ordenes_trabajo o JOIN vehiculos v ON v.id=o.vehiculo_id JOIN clientes c ON c.id=v.cliente_id';
        if ($soloAsignadas) $sql .= ' JOIN orden_mecanicos om ON om.orden_id=o.id JOIN mecanicos m ON m.id=om.mecanico_id';
        $condiciones=[]; $params=[];
        if ($soloAsignadas) {
            $condiciones[]='(m.usuario_id=:usuario_id'.($mecanico !== '' ? ' OR (m.usuario_id IS NULL AND m.nombre=:mecanico)' : '').')';
            $params['usuario_id']=$usuarioId;
            if ($mecanico !== '') $params['mecanico']=$mecanico;
        }
        if($busqueda!==''){
            $condiciones[]='(o.codigo LIKE :codigo OR c.nombre LIKE :cliente OR v.placa LIKE :placa OR v.marca LIKE :marca OR v.modelo LIKE :modelo)';
            $termino='%'.$busqueda.'%';
            foreach(['codigo','cliente','placa','marca','modelo'] as $campo)$params[$campo]=$termino;
        }
        if($estado!==null){
            $condiciones[]=$estado==='lista' ? "o.estado IN ('lista','listo')" : 'o.estado=:estado';
            if($estado!=='lista')$params['estado']=$estado;
        }
        if($condiciones)$sql.=' WHERE '.implode(' AND ',$condiciones);
        $sql.=' ORDER BY o.fecha_ingreso DESC';$s=$this->conexion->prepare($sql);$s->execute($params);return $s->fetchAll();
    }
    public function vehiculos(): array {$s=$this->conexion->query('SELECT v.*,c.nombre cliente FROM vehiculos v JOIN clientes c ON c.id=v.cliente_id ORDER BY v.placa');return $s->fetchAll();}
    public function asignadaA(int $orden, string $mecanico, int $usuarioId=0): bool {
        $s=$this->conexion->prepare('SELECT 1 FROM orden_mecanicos om JOIN mecanicos m ON m.id=om.mecanico_id WHERE om.orden_id=:orden AND (m.usuario_id=:usuario_id OR (m.usuario_id IS NULL AND m.nombre=:mecanico)) LIMIT 1');
        $s->execute(['orden'=>$orden,'usuario_id'=>$usuarioId,'mecanico'=>$mecanico]);
        return (bool) $s->fetchColumn();
    }
    public function crear(array $d): int {
        $this->conexion->beginTransaction();
        try {
            $code='OT-'.date('Ymd').'-'.random_int(100,999);
            $s=$this->conexion->prepare('INSERT INTO ordenes_trabajo (codigo,vehiculo_id,usuario_id,fecha_ingreso,fecha_prevista_retorno,diagnostico,observaciones,estado) VALUES (:code,:vehicle,:user,:ingreso,:retorno,:diagnostico,:observaciones,\'registrada\')');
            $s->execute(['code'=>$code,'vehicle'=>$d['vehiculo'],'user'=>$d['usuario'],'ingreso'=>$d['ingreso'],'retorno'=>$d['retorno'],'diagnostico'=>$d['diagnostico'],'observaciones'=>$d['observaciones']]);
            $id=(int)$this->conexion->lastInsertId();
            $h=$this->conexion->prepare('INSERT INTO historial_estados (orden_id,usuario_id,estado_anterior,estado_nuevo,observaciones) VALUES (:o,:u,NULL,\'registrada\',\'Orden creada\')');
            $h->execute(['o'=>$id,'u'=>$d['usuario']]);
            $a=$this->conexion->prepare('INSERT INTO orden_mecanicos (orden_id,mecanico_id) VALUES (:orden,:mecanico)');
            $validar=$this->conexion->prepare('SELECT COUNT(*) FROM mecanicos WHERE id=:id AND activo=1');
            foreach (($d['mecanicos'] ?? []) as $mecanicoId) {
                $validar->execute(['id'=>$mecanicoId]);
                if ((int)$validar->fetchColumn()!==1) throw new InvalidArgumentException('El mecánico seleccionado no está disponible.');
                $a->execute(['orden'=>$id,'mecanico'=>$mecanicoId]);
            }
            $this->conexion->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }
    public function detalle(int $id): ?array {
        $s=$this->conexion->prepare('SELECT o.*,v.placa,v.marca,v.modelo,v.anio,c.nombre cliente,c.telefono,c.direccion FROM ordenes_trabajo o JOIN vehiculos v ON v.id=o.vehiculo_id JOIN clientes c ON c.id=v.cliente_id WHERE o.id=:id LIMIT 1');$s->execute(['id'=>$id]);$o=$s->fetch();if(!$o)return null;$base=['orden'=>$o];
        foreach(['tareas'=>'SELECT * FROM tareas_orden WHERE orden_id=:id ORDER BY id DESC','pagos'=>'SELECT p.*,u.nombre usuario FROM pagos p JOIN usuarios u ON u.id=p.usuario_id WHERE p.orden_id=:id ORDER BY p.fecha_pago DESC','historial'=>'SELECT h.*,u.nombre usuario FROM historial_estados h JOIN usuarios u ON u.id=h.usuario_id WHERE h.orden_id=:id ORDER BY h.fecha_cambio DESC'] as $key=>$sql){$q=$this->conexion->prepare($sql);$q->execute(['id'=>$id]);$base[$key]=$q->fetchAll();}
        $q=$this->conexion->prepare('SELECT mecanico_id FROM orden_mecanicos WHERE orden_id=:id');
        $q->execute(['id'=>$id]);
        $base['mecanicos_asignados']=$q->fetchAll(PDO::FETCH_COLUMN);
        $q=$this->conexion->prepare('SELECT m.id,m.nombre FROM orden_mecanicos om JOIN mecanicos m ON m.id=om.mecanico_id WHERE om.orden_id=:id ORDER BY m.nombre');
        $q->execute(['id'=>$id]);
        $base['mecanicos']=$q->fetchAll();
        return $base;
    }
    public function estado(int $id,int $usuario,string $estado,string $observacion): void {
        $this->conexion->beginTransaction();try{$q=$this->conexion->prepare('SELECT estado,vehiculo_id FROM ordenes_trabajo WHERE id=:id FOR UPDATE');$q->execute(['id'=>$id]);$actual=$q->fetch();if(!$actual)throw new InvalidArgumentException('Orden no encontrada.');$u=$this->conexion->prepare('UPDATE ordenes_trabajo SET estado=:estado,fecha_entrega=CASE WHEN :estado2=\'entregada\' THEN CURRENT_TIMESTAMP ELSE fecha_entrega END WHERE id=:id');$u->execute(['estado'=>$estado,'estado2'=>$estado,'id'=>$id]);
            $estadoVehiculo = match ($estado) {
                'en_revision','diagnostico' => 'en_revision',
                'en_reparacion','pendiente_repuesto' => 'en_reparacion',
                'lista','listo' => 'listo',
                'entregada' => 'entregado',
                default => null,
            };
            if ($estadoVehiculo !== null) {$s=$this->conexion->prepare('UPDATE vehiculos SET estado=:estado WHERE id=:vehiculo');$s->execute(['estado'=>$estadoVehiculo,'vehiculo'=>$actual['vehiculo_id']]);}
            $h=$this->conexion->prepare('INSERT INTO historial_estados (orden_id,usuario_id,estado_anterior,estado_nuevo,observaciones) VALUES (:id,:usuario,:anterior,:nuevo,:obs)');$h->execute(['id'=>$id,'usuario'=>$usuario,'anterior'=>$actual['estado'],'nuevo'=>$estado,'obs'=>mb_substr($observacion,0,255,'UTF-8')]);$this->conexion->commit();}catch(Throwable $e){$this->conexion->rollBack();throw $e;}
    }
    public function actualizarTecnico(int $id,string $diagnostico,string $observaciones,?string $retorno,int $usuario): void {
        $this->conexion->beginTransaction();
        try {
            $validar=$this->conexion->prepare('SELECT id FROM ordenes_trabajo WHERE id=:id FOR UPDATE');
            $validar->execute(['id'=>$id]);
            if(!$validar->fetchColumn())throw new InvalidArgumentException('Orden no encontrada.');
            $actualizar=$this->conexion->prepare('UPDATE ordenes_trabajo SET diagnostico=:diagnostico,observaciones=:observaciones,fecha_prevista_retorno=:retorno WHERE id=:id');
            $actualizar->execute(['diagnostico'=>$diagnostico,'observaciones'=>$observaciones,'retorno'=>$retorno,'id'=>$id]);
            $estado=$this->conexion->prepare('SELECT estado FROM ordenes_trabajo WHERE id=:id');
            $estado->execute(['id'=>$id]);
            $valor=(string)$estado->fetchColumn();
            $historial=$this->conexion->prepare('INSERT INTO historial_estados (orden_id,usuario_id,estado_anterior,estado_nuevo,observaciones) VALUES (:id,:usuario,:estado,:estado_nuevo,:observacion)');
            $historial->execute(['id'=>$id,'usuario'=>$usuario,'estado'=>$valor,'estado_nuevo'=>$valor,'observacion'=>'Actualización técnica registrada por el mecánico']);
            $this->conexion->commit();
        } catch(Throwable $e) {$this->conexion->rollBack();throw $e;}
    }
    public function editar(int $id,int $vehiculo,?string $retorno,string $estado,string $diagnostico,string $observaciones,?float $costo,array $mecanicos,int $usuario): void {
        $this->conexion->beginTransaction();
        try {
            $actual=$this->conexion->prepare('SELECT estado FROM ordenes_trabajo WHERE id=:id FOR UPDATE');
            $actual->execute(['id'=>$id]);
            $anterior=$actual->fetchColumn();
            if($anterior===false)throw new InvalidArgumentException('Orden no encontrada.');
            $editar=$this->conexion->prepare('UPDATE ordenes_trabajo SET vehiculo_id=:vehiculo,fecha_prevista_retorno=:retorno,estado=:estado,diagnostico=:diagnostico,observaciones=:observaciones,costo_final=COALESCE(:costo,costo_final) WHERE id=:id');
            $editar->execute(['vehiculo'=>$vehiculo,'retorno'=>$retorno,'estado'=>$estado,'diagnostico'=>$diagnostico,'observaciones'=>$observaciones,'costo'=>$costo,'id'=>$id]);
            $validar=$this->conexion->prepare('SELECT COUNT(*) FROM mecanicos WHERE id=:id AND activo=1');
            $insertar=$this->conexion->prepare('INSERT INTO orden_mecanicos (orden_id,mecanico_id) VALUES (:orden,:mecanico)');
            $this->conexion->prepare('DELETE FROM orden_mecanicos WHERE orden_id=:id')->execute(['id'=>$id]);
            foreach(array_values(array_unique($mecanicos)) as $mecanicoId){
                $validar->execute(['id'=>$mecanicoId]);
                if((int)$validar->fetchColumn()!==1)throw new InvalidArgumentException('El mecánico seleccionado no está disponible.');
                $insertar->execute(['orden'=>$id,'mecanico'=>$mecanicoId]);
            }
            if((string)$anterior!==$estado){
                $historial=$this->conexion->prepare('INSERT INTO historial_estados (orden_id,usuario_id,estado_anterior,estado_nuevo,observaciones) VALUES (:orden,:usuario,:anterior,:nuevo,:observacion)');
                $historial->execute(['orden'=>$id,'usuario'=>$usuario,'anterior'=>$anterior,'nuevo'=>$estado,'observacion'=>'Orden editada por administrador']);
            }
            $this->conexion->commit();
        } catch(Throwable $e){if($this->conexion->inTransaction())$this->conexion->rollBack();throw $e;}
    }
    public function actualizarPrecio(int $orden,float $precio): void {
        $validar=$this->conexion->prepare('SELECT id FROM ordenes_trabajo WHERE id=:id LIMIT 1');
        $validar->execute(['id'=>$orden]);
        if(!$validar->fetchColumn())throw new InvalidArgumentException('Orden no encontrada.');
        $actualizar=$this->conexion->prepare('UPDATE ordenes_trabajo SET costo_final=:precio,precio_confirmado=1 WHERE id=:id');
        $actualizar->execute(['precio'=>$precio,'id'=>$orden]);
    }
    public function eliminar(int $id,int $usuario): void {
        $this->conexion->beginTransaction();
        try {
            $validar=$this->conexion->prepare('SELECT id FROM ordenes_trabajo WHERE id=:id FOR UPDATE');
            $validar->execute(['id'=>$id]);
            if(!$validar->fetchColumn())throw new InvalidArgumentException('Orden no encontrada.');
            foreach(['movimientos_inventario','historial_estados','pagos','repuestos_orden','tareas_orden','orden_mecanicos'] as $tabla){
                $sql=$this->conexion->prepare("DELETE FROM {$tabla} WHERE orden_id=:id");
                $sql->execute(['id'=>$id]);
            }
            $eliminar=$this->conexion->prepare('DELETE FROM ordenes_trabajo WHERE id=:id');
            $eliminar->execute(['id'=>$id]);
            $this->conexion->commit();
        } catch(Throwable $e){if($this->conexion->inTransaction())$this->conexion->rollBack();throw $e;}
    }
    public function agregarTareasCatalogo(int $orden,array $tareas): int {
        $this->conexion->beginTransaction();
        try {
            $validarOrden=$this->conexion->prepare('SELECT id FROM ordenes_trabajo WHERE id=:orden FOR UPDATE');
            $validarOrden->execute(['orden'=>$orden]);
            if(!$validarOrden->fetchColumn())throw new InvalidArgumentException('Orden no encontrada.');
            $buscar=$this->conexion->prepare('SELECT id,costo FROM tareas_orden WHERE orden_id=:orden AND descripcion=:descripcion LIMIT 1');
            $insertar=$this->conexion->prepare('INSERT INTO tareas_orden (orden_id,descripcion,costo,estado) VALUES (:orden,:descripcion,:costo,\'pendiente\')');
            $actualizarTarea=$this->conexion->prepare('UPDATE tareas_orden SET costo=:costo WHERE id=:id AND orden_id=:orden AND costo IS NULL');
            $agregadas=0;
            $montoAgregado=0.0;
            foreach(array_values(array_unique($tareas)) as $tarea){
                $buscar->execute(['orden'=>$orden,'descripcion'=>$tarea]);
                $precio=$this->precioReferencial($tarea);
                $existente=$buscar->fetch(PDO::FETCH_ASSOC);
                if($existente){
                    if($existente['costo']!==null)continue;
                    $actualizarTarea->execute(['costo'=>$precio,'id'=>$existente['id'],'orden'=>$orden]);
                    if($actualizarTarea->rowCount()>0){$agregadas++;$montoAgregado+=$precio;}
                    continue;
                }
                $insertar->execute(['orden'=>$orden,'descripcion'=>$tarea,'costo'=>$precio]);
                $agregadas++;
                $montoAgregado+=$precio;
            }
            if($agregadas>0){
                $actualizarPrecio=$this->conexion->prepare('UPDATE ordenes_trabajo SET costo_final=COALESCE(costo_final,0)+:monto,precio_confirmado=0 WHERE id=:orden');
                $actualizarPrecio->execute(['monto'=>$montoAgregado,'orden'=>$orden]);
            }
            $this->conexion->commit();
            return $agregadas;
        } catch(Throwable $e){if($this->conexion->inTransaction())$this->conexion->rollBack();throw $e;}
    }
    public function completarTareas(int $orden,array $ids): int {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),function($id){return $id>0;})));
        if(!$ids)throw new InvalidArgumentException('Seleccione al menos un trabajo.');
        $this->conexion->beginTransaction();
        try {
            $validarOrden=$this->conexion->prepare('SELECT id FROM ordenes_trabajo WHERE id=:orden FOR UPDATE');
            $validarOrden->execute(['orden'=>$orden]);
            if(!$validarOrden->fetchColumn())throw new InvalidArgumentException('Orden no encontrada.');
            $marcadores=implode(',',array_fill(0,count($ids),'?'));
            $validar=$this->conexion->prepare("SELECT COUNT(*) FROM tareas_orden WHERE orden_id=? AND id IN ({$marcadores})");
            $validar->execute(array_merge([$orden],$ids));
            if((int)$validar->fetchColumn()!==count($ids))throw new InvalidArgumentException('Una o más tareas no pertenecen a esta orden.');
            $actualizar=$this->conexion->prepare("UPDATE tareas_orden SET estado='completada' WHERE orden_id=? AND id IN ({$marcadores})");
            $actualizar->execute(array_merge([$orden],$ids));
            $completadas=$actualizar->rowCount();
            $this->conexion->commit();
            return $completadas;
        } catch(Throwable $e){if($this->conexion->inTransaction())$this->conexion->rollBack();throw $e;}
    }
    public function resumenTareas(int $orden): array {
        $s=$this->conexion->prepare("SELECT COUNT(*) AS total,SUM(CASE WHEN estado<>'completada' THEN 1 ELSE 0 END) AS pendientes,COALESCE(SUM(costo),0) AS monto FROM tareas_orden WHERE orden_id=:orden");
        $s->execute(['orden'=>$orden]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'pendientes'=>0,'monto'=>0];
    }
    public function tarea(int $orden,string $descripcion,string $estado,$costo): bool {
        $this->conexion->beginTransaction();
        try {
            $s=$this->conexion->prepare('INSERT INTO tareas_orden (orden_id,descripcion,costo,estado) VALUES (:orden,:descripcion,:costo,:estado)');
            $guardada=$s->execute(['orden'=>$orden,'descripcion'=>mb_substr($descripcion,0,255,'UTF-8'),'costo'=>$costo,'estado'=>$estado]);
            if($guardada&&$costo!==null){
                $actualizar=$this->conexion->prepare('UPDATE ordenes_trabajo SET costo_final=COALESCE(costo_final,0)+:costo,precio_confirmado=0 WHERE id=:orden');
                $actualizar->execute(['costo'=>$costo,'orden'=>$orden]);
            }
            $this->conexion->commit();
            return $guardada;
        } catch(Throwable $e){if($this->conexion->inTransaction())$this->conexion->rollBack();throw $e;}
    }
    public function pago(int $orden,int $usuario,float $monto,string $metodo,string $referencia,string $observacion): bool {$s=$this->conexion->prepare('INSERT INTO pagos (orden_id,usuario_id,monto,metodo,referencia,observaciones) VALUES (:orden,:usuario,:monto,:metodo,:referencia,:observaciones)');return $s->execute(['orden'=>$orden,'usuario'=>$usuario,'monto'=>$monto,'metodo'=>$metodo,'referencia'=>mb_substr($referencia,0,100,'UTF-8'),'observaciones'=>mb_substr($observacion,0,255,'UTF-8')]);}
}
