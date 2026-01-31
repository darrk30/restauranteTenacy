<?php

namespace App\Filament\Restaurants\Resources\SessionCashRegisterResource\Pages;

use App\Filament\Restaurants\Resources\SessionCashRegisterResource;
use App\Models\PaymentMethod;
use App\Models\SessionCashRegister;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Necesario para la transacción
use Illuminate\Database\Eloquent\Model;

class CreateSessionCashRegister extends CreateRecord
{
    protected static string $resource = SessionCashRegisterResource::class;

    // 1. Preparar datos antes de crear (Generar código, asignar usuario)
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['session_code'] = 'SESSION-' . now()->format('Ymd-His');
        $data['status'] = 'open';
        $data['user_id'] = Auth::id();
        
        return $data;
    }

    // 2. Validación inicial al cargar la página
    public function mount(): void
    {
        // A. Buscamos si el usuario tiene una sesión ABIERTA
        $sesionAbierta = SessionCashRegister::where('user_id', Auth::id())
            ->whereNull('closed_at') // O where('status', 'open')
            ->first();

        // B. Si YA tiene una abierta, lo redirigimos a la pantalla de EDICIÓN (Cierre)
        if ($sesionAbierta) {
            Notification::make()
                ->title('Ya tienes una caja abierta')
                ->body('Redirigiendo al cierre de caja...')
                ->warning()
                ->send();

            // Redirección forzada a la página de edición
            $this->redirect(SessionCashRegisterResource::getUrl('edit', ['record' => $sesionAbierta]));
            return; // Detenemos la ejecución
        }

        // Si no tiene abierta, continúa normal
        parent::mount(); 
    }

    // 3. Lógica ATÓMICA de creación (Aquí reemplazamos a afterCreate)
    protected function handleRecordCreation(array $data): Model
    {
        // A. VALIDACIÓN PREVIA: Verificar existencia del método de pago
        // Buscamos "Efectivo", "efectivo", "EFECTIVO"
        $efectivo = PaymentMethod::where('name', 'like', '%Efectivo%')->first();

        // Si no existe, CANCELAMOS TODO
        if (! $efectivo) {
            Notification::make()
                ->title('Error de Configuración')
                ->body('No se puede abrir caja: No se encontró el método de pago "Efectivo" en el sistema. Por favor, créalo primero.')
                ->danger()
                ->persistent() // El mensaje se queda pegado para que lo lean
                ->send();

            $this->halt(); // 🛑 ESTO DETIENE EL GUARDADO. No se crea la sesión.
        }

        // B. TRANSACCIÓN: Todo o Nada
        return DB::transaction(function () use ($data, $efectivo) {
            
            // 1. Crear la Sesión de Caja
            // (static::getModel() obtiene el modelo definido en el recurso)
            $sesion = static::getModel()::create($data);

            // 2. Crear el Movimiento de Apertura
            // Gracias a tu Observer, esto también creará la fila en 'cierre_caja_detalles'
            $sesion->cashRegisterMovements()->create([
                'session_cash_register_id' => $sesion->id,
                'payment_method_id' => $efectivo->id,
                'usuario_id'     => Auth::id(),
                'tipo'           => 'ingreso',
                'motivo'         => 'apertura',
                'monto'          => $data['opening_amount'], // El monto que ingresó en el formulario
                'observacion'    => 'Monto inicial de apertura de caja',
            ]);

            return $sesion;
        });
    }

    // Opcional: Redireccionar al índice después de crear exitosamente
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}