<?php

namespace App\Modules\Addons\SmartImportExport\Services;

use App\Models\ActiveEquipment;
use App\Models\ActiveEquipmentPeripheral;
use App\Models\ActiveEquipmentType;
use App\Models\Balance;
use App\Models\BillingAddress;
use App\Models\Box;
use App\Models\BoxInput;
use App\Models\BoxType;
use App\Models\BoxZone;
use App\Models\Brand;
use App\Models\Buffer;
use App\Models\Bundle;
use App\Models\Card;
use App\Models\Client;
use App\Models\ClientAdditionalInformation;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientGracePeriod;
use App\Models\ClientInternetService;
use App\Models\ClientInvoice;
use App\Models\ClientInvoiceService;
use App\Models\ClientMainInformation;
use App\Models\ClientPaymentMetadata;
use App\Models\ClientPaymentPromise;
use App\Models\ClientPaymentService;
use App\Models\ClientUser;
use App\Models\ClientVozService;
use App\Models\Colony;
use App\Models\Color;
use App\Models\ColumnDatatableModule;
use App\Models\Commission;
use App\Models\CommissionDetail;
use App\Models\CommissionRule;
use App\Models\Credential;
use App\Models\Custom;
use App\Models\CutBox;
use App\Models\CutExtraIncome;
use App\Models\CutFiber;
use App\Models\CutInstallation;
use App\Models\CutObservation;
use App\Models\CutSupplierExpense;
use App\Models\Discount;
use App\Models\DiscountSale;
use App\Models\DistributionCommission;
use App\Models\DistributionCommissionAmount;
use App\Models\District;
use App\Models\DocumentClient;
use App\Models\DocumentTemplate;
use App\Models\DocumentTypeTemplate;
use App\Models\DocumentationContent;
use App\Models\DocumentationMenu;
use App\Models\DocumentationSubmenu;
use App\Models\DurationContract;
use App\Models\EmailSetting;
use App\Models\EquipmentLink;
use App\Models\Fiber;
use App\Models\File as FileModel;
use App\Models\FrequencyCommand;
use App\Models\FrequencyEstimatedDedicatedTime;
use App\Models\GeneralAccountingCategory;
use App\Models\GeneralAccountingExpense;
use App\Models\GeneralAccountingIncome;
use App\Models\GeneralAccountingOperation;
use App\Models\GeneralAccountingType;
use App\Models\GeneralConfigurationRule;
use App\Models\HistoryGeneralConfigurationRule;
use App\Models\HistorySellerRule;
use App\Models\Ift;
use App\Models\Internet;
use App\Models\InventoryItem;
use App\Models\InventoryItemCustomModel;
use App\Models\InventoryItemMedia;
use App\Models\InventoryItemStock;
use App\Models\InventoryItemStoreZone;
use App\Models\InventoryItemType;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\InventoryStore;
use App\Models\Invoice;
use App\Models\InvoiceEmail;
use App\Models\InvoiceItem;
use App\Models\ListTemplateVerification;
use App\Models\ListTemplateVerificationTask;
use App\Models\Location;
use App\Models\MapCredential;
use App\Models\MapCutFiber;
use App\Models\MapDevice;
use App\Models\MapDevicePort;
use App\Models\MapDevicePortConnection;
use App\Models\MapFiber;
use App\Models\MapLayer;
use App\Models\MapLayerRoute;
use App\Models\MapLink;
use App\Models\MapPort;
use App\Models\MapProyect;
use App\Models\MapRoute;
use App\Models\MediumOfSale;
use App\Models\MethodOfPayment;
use App\Models\Mikrotik;
use App\Models\MikrotikClientHostpotRadius;
use App\Models\MikrotikClientHostpotUser;
use App\Models\MikrotikClientPpoe;
use App\Models\MikrotikConfig;
use App\Models\MikrotikItemToExcecuteAction;
use App\Models\MikrotikNotification;
use App\Models\MikrotikTariffMainTail;
use App\Models\MikrotikTariffTargetTail;
use App\Models\Modem;
use App\Models\Module;
use App\Models\Municipality;
use App\Models\Network;
use App\Models\NetworkIp;
use App\Models\Nomenclature;
use App\Models\ObservationTask;
use App\Models\Olt;
use App\Models\OltBilling;
use App\Models\OltCard;
use App\Models\OltInterruptionPon;
use App\Models\OltOdb;
use App\Models\OltOnu;
use App\Models\OltPonPort;
use App\Models\OltSpeedProfile;
use App\Models\OltTypeONU;
use App\Models\OltUnconfiguredOnu;
use App\Models\OltUplinkPort;
use App\Models\OltVlan;
use App\Models\OltZone;
use App\Models\Package;
use App\Models\Partner;
use App\Models\PassiveEquipment;
use App\Models\PassiveEquipmentType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentByRule;
use App\Models\PaymentByRuleDetails;
use App\Models\PaymentDetail;
use App\Models\PaymentEmail;
use App\Models\PaymentPromise;
use App\Models\PaymentSeller;
use App\Models\Point;
use App\Models\PointAccessory;
use App\Models\Pole;
use App\Models\PoleAccessory;
use App\Models\Port;
use App\Models\Position;
use App\Models\ProformaInvoiceEmail;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\QuoteCrm;
use App\Models\Rack;
use App\Models\RangeSale;
use App\Models\Receipt;
use App\Models\Release;
use App\Models\ReleaseDescription;
use App\Models\Reminder;
use App\Models\RemindersConfiguration;
use App\Models\Router;
use App\Models\Seller;
use App\Models\SellerStatus;
use App\Models\SellerType;
use App\Models\ServiceInAddressList;
use App\Models\SettingDebtPaymentClientCustom;
use App\Models\SettingDebtPaymentClientRecurrent;
use App\Models\SettingTable;
use App\Models\SettingToolsImport;
use App\Models\Site;
use App\Models\Splitter;
use App\Models\State;
use App\Models\StoreZone;
use App\Models\Sucursal;
use App\Models\SystemUser;
use App\Models\Table;
use App\Models\Task;
use App\Models\TaskNotification;
use App\Models\Tax;
use App\Models\Team;
use App\Models\TemplateTask;
use App\Models\TemplateVerification;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\Transaction;
use App\Models\TransactionSeller;
use App\Models\Transceiver;
use App\Models\Tray;
use App\Models\Trench;
use App\Models\TrencheTypes;
use App\Models\Tube;
use App\Models\TubeType;
use App\Models\TypeBilling;
use App\Models\User;
use App\Models\UserColumnDatatableExpand;
use App\Models\UserColumnDatatableModule;
use App\Models\Voise;
use App\Models\WorkFlow;
use App\Models\Zone;
use App\Modules\Addons\EvaluadorEmpresarial\Models\EvaluacionEmpresarial;
use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAMemoriaProyecto;
use App\Modules\Addons\IA\Models\IAMensaje;
use App\Modules\Addons\IA\Models\IAMessageFile;
use App\Modules\Addons\IA\Models\IANotaProyecto;
use App\Modules\Addons\IA\Models\IAPromptUsuario;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAProyecto;
use App\Modules\Addons\IA\Models\IASesionTrabajo;
use App\Modules\Addons\IA\Models\IATarea;
use App\Modules\Addons\IA\Models\IAUsoToken;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Addons\MegaFamilia\Models\AppVersion;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalAlert;
use App\Modules\Addons\MegaFamilia\Models\ParentalAppBlock;
use App\Modules\Addons\MegaFamilia\Models\ParentalConsent;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalGeofence;
use App\Modules\Addons\MegaFamilia\Models\ParentalLicense;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalRule;
use App\Modules\Addons\MegaFamilia\Models\ParentalSchedule;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use App\Modules\Addons\MegaFamilia\Models\ParentalWebBlock;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Core\CRM\Models\Crm;
use App\Modules\Core\CRM\Models\CrmLeadInformation;
use App\Modules\Core\CRM\Models\CrmMainInformation;
use App\Modules\Core\CRM\Models\DealCrm;
use App\Modules\Core\CRM\Models\DocumentCrm;
use App\Modules\Core\Configuracion\Models\ApiMobileConfig;
use App\Modules\Core\Configuracion\Models\BillingConfiguration;
use App\Modules\Core\Configuracion\Models\BillingReminder;
use App\Modules\Core\Configuracion\Models\CommandConfig;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use App\Modules\Core\Configuracion\Models\ConfigFinanceNotification;
use App\Modules\Core\Configuracion\Models\DefaultValue;
use App\Modules\Core\Configuracion\Models\FieldModule;
use App\Modules\Core\Configuracion\Models\FieldType;
use App\Modules\Core\Layout\Models\AppLayoutConfiguration;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use Throwable;

class SmartImportService
{
    /**
     * Counts exactos por tabla del último parseSql() — incluso si truncamos
     * el dataset cargado en RAM. analyzeFile() los usa para 'records' real.
     * @var array<string, int>
     */
    private array $lastRowCounts = [];

    /**
     * Mapa tabla → orden ORIGINAL de columnas tomado de CREATE TABLE del
     * dump. Crítico para schema drift: cuando el dump es más viejo que la
     * BD destino (dump tiene 23 cols, BD tiene 26 cols), array_combine con
     * Schema::getColumnListing() da cardinality mismatch y sanitizeRow
     * devolvía [] silenciosamente. Con este mapa hacemos array_combine
     * contra las columnas del DUMP (lo que realmente viene en cada tuple),
     * luego array_intersect_key contra la BD destino — las columnas nuevas
     * en BD quedan en NULL/default y las eliminadas del dump se ignoran.
     *
     * Se pobla en extractDumpColumns() (pre-pass de parseSql) y se persiste
     * en el cache del analysis. SmartImportJob::handle() lo rehidrata vía
     * setDumpColumns() antes de procesar las tablas.
     *
     * @var array<string, string[]>
     */
    private array $dumpColumnsByTable = [];

    /**
     * Metadata del esquema del dump SQL/ZIP.
     *
     * @var array<string, array{
     *     table: string,
     *     columns: string[],
     *     column_definitions: array<string, string>,
     *     key_definitions: string[],
     *     foreign_key_definitions: string[],
     *     dependencies: string[],
     *     create_sql: string,
     *     table_options: string
     * }>
     */
    private array $sourceTableSchemas = [];

    /**
     * Cache de Schema::hasColumn() para evitar consultas repetidas a information_schema.
     * @var array<string, array<string, bool>>
     */
    private array $hasColumnCache = [];

    /**
     * Cache de metadata derivada de getColumnListing() para evitar resolver
     * las mismas columnas por fila durante sanitize/inject/bulk normalize.
     *
     * @var array<string, array{
     *     columns: string[],
     *     columns_flip: array<string, bool>,
     *     has_created_at: bool,
     *     has_updated_at: bool,
     *     has_created_by: bool,
     *     has_updated_by: bool
     * }>
     */
    private array $tableMetadataCache = [];

    /**
     * Cache de Schema::hasTable() para evitar consultas repetidas durante el parser streaming.
     * @var array<string, bool>
     */
    private array $targetTableExistsCache = [];

    /**
     * Cache de dependencias FK existentes en la DB destino.
     * @var array<string, string[]>
     */
    private array $targetForeignKeyDependencyCache = [];

    /**
     * Cache de metadata FK existente en la DB destino.
     * @var array<string, array<string, array{column: string, referenced_table: string, referenced_column: string, nullable: bool}>>
     */
    private array $targetForeignKeyMetadataCache = [];

    /**
     * Cache de existencia de valores referenciados por FK.
     * @var array<string, bool>
     */
    private array $referencedValueExistsCache = [];

    /**
     * Cache de fallback por referencia FK no-nullable.
     * @var array<string, array{available: bool, value: mixed}>
     */
    private array $foreignKeyFallbackValueCache = [];

    /** Profundidad de bloques con FOREIGN_KEY_CHECKS desactivado en la conexion actual. */
    private int $foreignKeyChecksDisabledDepth = 0;

    /** Usuario que ejecuta el import en contexto async. */
    private ?int $actingUserId = null;

    private ?SmartImportTableResolver $tableResolver = null;

    /**
     * Modelos que deben quedarse en camino estricto: observers, jobs o lógica
     * de dominio embebida al crear/actualizar.
     *
     * @var class-string[]
     */
    private const STRICT_MODEL_CLASSES = [
        Balance::class,
        BillingConfiguration::class,
        Client::class,
        ClientBundleService::class,
        ClientCustomService::class,
        ClientInternetService::class,
        ClientInvoice::class,
        ClientMainInformation::class,
        ClientUser::class,
        ClientVozService::class,
        CompanyInformation::class,
        CrmMainInformation::class,
        CutExtraIncome::class,
        CutInstallation::class,
        CutObservation::class,
        CutSupplierExpense::class,
        Discount::class,
        DistributionCommission::class,
        DocumentationContent::class,
        DocumentationMenu::class,
        DocumentationSubmenu::class,
        EvaluacionEmpresarial::class,
        FieldModule::class,
        FileModel::class,
        IANotaProyecto::class,
        IATarea::class,
        InventoryItem::class,
        InventoryItemMedia::class,
        InventoryItemType::class,
        InventoryMovement::class,
        MapDevicePortConnection::class,
        MapLayer::class,
        MapRoute::class,
        Mikrotik::class,
        MikrotikConfig::class,
        ModuleRegistry::class,
        Network::class,
        Payment::class,
        PaymentByRule::class,
        Seller::class,
        Task::class,
        WhatsAppInstance::class,
    ];

    private const BULK_CHUNK_SIZE = 500;
    private const PREVIEW_CONFLICT_CHUNK_SIZE = 500;
    private const PREVIEW_CONFLICT_DETAIL_LIMIT = 50;
    public const GLOBAL_MODE_FORCE_SOURCE = 'force_source';
    public const GLOBAL_MODE_SKIP_EXISTING = 'skip_existing';
    public const GLOBAL_MODE_SMART = 'smart';
    private const SUPPORTED_IMPORT_FORMATS = ['sql', 'zip'];

    public function setDumpColumns(array $dumpColumns): void
    {
        $this->dumpColumnsByTable = $dumpColumns;
    }

    public function setSourceTableSchemas(array $schemas): void
    {
        $this->sourceTableSchemas = $schemas;
    }

    public function setActingUserId(?int $userId): void
    {
        $this->actingUserId = $userId;
        if ($userId !== null) {
            try {
                Auth::onceUsingId($userId);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Mapa de tabla → módulo lógico + modelo Eloquent que la administra.
     * El frontend usa esto para enseñar el reporte y resolver conflictos.
     *
     * Cada entrada puede tener:
     *   - 'module'        — etiqueta cosmética para el reporte
     *   - 'model'         — clase Eloquent FQCN (mode='model', default)
     *   - 'mode' => 'raw' — tablas sin modelo Eloquent; executeImport() debe
     *                       insertar vía DB::table() en vez de $modelClass::create()
     *   - 'conflict_keys' — columnas que definen duplicado. [] = solo-inserción
     *                       (ver paso 6 del plan: defaultear vacío es seguro;
     *                       autoritativo cuando vienen de UNIQUE indexes).
     */
    public const TABLE_MODULE_MAP = [
        // ─── API Mobile ─── (1)
        'api_mobile_config'                        => ['module' => 'API Mobile', 'model' => ApiMobileConfig::class, 'conflict_keys' => ['key']],

        // ─── Administración ─── (6)
        'email_settings'                           => ['module' => 'Administración', 'model' => EmailSetting::class, 'conflict_keys' => []],
        'permissions'                              => ['module' => 'Administración', 'mode' => 'raw', 'conflict_keys' => ['name', 'guard_name']],
        'positions'                                => ['module' => 'Administración', 'model' => Position::class, 'conflict_keys' => []],
        'roles'                                    => ['module' => 'Administración', 'mode' => 'raw', 'conflict_keys' => ['name', 'guard_name']],
        'system_users'                             => ['module' => 'Administración', 'model' => SystemUser::class, 'conflict_keys' => []],
        'teams'                                    => ['module' => 'Administración', 'model' => Team::class, 'conflict_keys' => []],

        // ─── CRM ─── (6)
        'crm_lead_information'                     => ['module' => 'CRM', 'model' => CrmLeadInformation::class, 'conflict_keys' => ['instalation_date']],
        'crm_main_information'                     => ['module' => 'CRM', 'model' => CrmMainInformation::class, 'conflict_keys' => []],
        'crms'                                     => ['module' => 'CRM', 'model' => Crm::class, 'conflict_keys' => ['email', 'phone', 'full_name']],
        'deal_crms'                                => ['module' => 'CRM', 'model' => DealCrm::class, 'conflict_keys' => []],
        'document_crms'                            => ['module' => 'CRM', 'model' => DocumentCrm::class, 'conflict_keys' => []],
        'quote_crms'                               => ['module' => 'CRM', 'model' => QuoteCrm::class, 'conflict_keys' => []],

        // ─── Cajas ─── (4)
        'box_inputs'                               => ['module' => 'Cajas', 'model' => BoxInput::class, 'conflict_keys' => []],
        'box_types'                                => ['module' => 'Cajas', 'model' => BoxType::class, 'conflict_keys' => []],
        'boxes'                                    => ['module' => 'Cajas', 'model' => Box::class, 'conflict_keys' => []],
        'colors'                                   => ['module' => 'Cajas', 'model' => Color::class, 'conflict_keys' => ['code']],

        // ─── Clientes ─── (16)
        'change_plan_internet_clients'             => ['module' => 'Clientes', 'mode' => 'raw', 'conflict_keys' => []],
        'change_plan_voz_clients'                  => ['module' => 'Clientes', 'mode' => 'raw', 'conflict_keys' => []],
        'client_additional_information'            => ['module' => 'Clientes', 'model' => ClientAdditionalInformation::class, 'conflict_keys' => []],
        'client_bundle_services'                   => ['module' => 'Clientes', 'model' => ClientBundleService::class, 'conflict_keys' => []],
        'client_custom_services'                   => ['module' => 'Clientes', 'model' => ClientCustomService::class, 'conflict_keys' => []],
        'client_grace_periods'                     => ['module' => 'Clientes', 'model' => ClientGracePeriod::class, 'conflict_keys' => []],
        'client_internet_services'                 => ['module' => 'Clientes', 'model' => ClientInternetService::class, 'conflict_keys' => []],
        'client_invoices'                          => ['module' => 'Clientes', 'model' => ClientInvoice::class, 'conflict_keys' => []],
        'client_main_information'                  => ['module' => 'Clientes', 'model' => ClientMainInformation::class, 'conflict_keys' => []],
        'client_payment_metadata'                  => ['module' => 'Clientes', 'model' => ClientPaymentMetadata::class, 'conflict_keys' => []],
        'client_payment_promises'                  => ['module' => 'Clientes', 'model' => ClientPaymentPromise::class, 'conflict_keys' => []],
        'client_payment_services'                  => ['module' => 'Clientes', 'model' => ClientPaymentService::class, 'conflict_keys' => []],
        'client_serviceables'                      => ['module' => 'Clientes', 'model' => ClientInvoiceService::class, 'conflict_keys' => []],
        'client_users'                             => ['module' => 'Clientes', 'model' => ClientUser::class, 'conflict_keys' => []],
        'client_voz_services'                      => ['module' => 'Clientes', 'model' => ClientVozService::class, 'conflict_keys' => []],
        'clients'                                  => ['module' => 'Clientes', 'model' => Client::class, 'conflict_keys' => ['email', 'phone', 'full_name']],

        // ─── Contabilidad ─── (5)
        'general_accounting_categories'            => ['module' => 'Contabilidad', 'model' => GeneralAccountingCategory::class, 'conflict_keys' => []],
        'general_accounting_expenses'              => ['module' => 'Contabilidad', 'model' => GeneralAccountingExpense::class, 'conflict_keys' => []],
        'general_accounting_incomes'               => ['module' => 'Contabilidad', 'model' => GeneralAccountingIncome::class, 'conflict_keys' => []],
        'general_accounting_operations'            => ['module' => 'Contabilidad', 'model' => GeneralAccountingOperation::class, 'conflict_keys' => []],
        'general_accounting_types'                 => ['module' => 'Contabilidad', 'model' => GeneralAccountingType::class, 'conflict_keys' => []],

        // ─── Cortes ─── (5)
        'cut_boxs'                                 => ['module' => 'Cortes', 'model' => CutBox::class, 'conflict_keys' => []],
        'cut_extras_incomes'                       => ['module' => 'Cortes', 'model' => CutExtraIncome::class, 'conflict_keys' => []],
        'cut_installations'                        => ['module' => 'Cortes', 'model' => CutInstallation::class, 'conflict_keys' => []],
        'cut_suppliers_expenses'                   => ['module' => 'Cortes', 'model' => CutSupplierExpense::class, 'conflict_keys' => []],
        'cuts_observations'                        => ['module' => 'Cortes', 'model' => CutObservation::class, 'conflict_keys' => []],

        // ─── Documentación ─── (4)
        'documentation_contents'                   => ['module' => 'Documentación', 'model' => DocumentationContent::class, 'conflict_keys' => []],
        'documentation_menus'                      => ['module' => 'Documentación', 'model' => DocumentationMenu::class, 'conflict_keys' => ['title']],
        'documentation_submenus'                   => ['module' => 'Documentación', 'model' => DocumentationSubmenu::class, 'conflict_keys' => ['documentation_menu_id', 'title']],
        'manual_sections'                          => ['module' => 'Documentación', 'model' => ManualSection::class, 'conflict_keys' => ['module_slug', 'version']],

        // ─── Documentos ─── (5)
        'credential_images'                        => ['module' => 'Documentos', 'model' => Credential::class, 'conflict_keys' => []],
        'document_clients'                         => ['module' => 'Documentos', 'model' => DocumentClient::class, 'conflict_keys' => []],
        'document_templates'                       => ['module' => 'Documentos', 'model' => DocumentTemplate::class, 'conflict_keys' => []],
        'document_type_templates'                  => ['module' => 'Documentos', 'model' => DocumentTypeTemplate::class, 'conflict_keys' => []],
        'files'                                    => ['module' => 'Documentos', 'model' => FileModel::class, 'conflict_keys' => []],

        // ─── Evaluador ─── (1)
        'evaluaciones_empresariales'               => ['module' => 'Evaluador', 'model' => EvaluacionEmpresarial::class, 'conflict_keys' => ['token_publico']],

        // ─── Finanzas ─── (26)
        'balances'                                 => ['module' => 'Finanzas', 'model' => Balance::class, 'conflict_keys' => []],
        'billing_addresses'                        => ['module' => 'Finanzas', 'model' => BillingAddress::class, 'conflict_keys' => []],
        'billing_configurations'                   => ['module' => 'Finanzas', 'model' => BillingConfiguration::class, 'conflict_keys' => []],
        'billing_reminders'                        => ['module' => 'Finanzas', 'model' => BillingReminder::class, 'conflict_keys' => []],
        'config_finance_notifications'             => ['module' => 'Finanzas', 'model' => ConfigFinanceNotification::class, 'conflict_keys' => ['type_config']],
        'discounts'                                => ['module' => 'Finanzas', 'model' => Discount::class, 'conflict_keys' => []],
        'discounts_sales'                          => ['module' => 'Finanzas', 'model' => DiscountSale::class, 'conflict_keys' => []],
        'invoice_emails'                           => ['module' => 'Finanzas', 'model' => InvoiceEmail::class, 'conflict_keys' => []],
        'invoice_items'                            => ['module' => 'Finanzas', 'model' => InvoiceItem::class, 'conflict_keys' => []],
        'invoices'                                 => ['module' => 'Finanzas', 'model' => Invoice::class, 'conflict_keys' => ['folio', 'client_id']],
        'method_of_payments'                       => ['module' => 'Finanzas', 'model' => MethodOfPayment::class, 'conflict_keys' => []],
        'payment_accounts'                         => ['module' => 'Finanzas', 'model' => PaymentAccount::class, 'conflict_keys' => []],
        'payment_by_rule'                          => ['module' => 'Finanzas', 'model' => PaymentByRule::class, 'conflict_keys' => []],
        'payment_by_rule_commissions'              => ['module' => 'Finanzas', 'model' => PaymentByRuleDetails::class, 'conflict_keys' => []],
        'payment_emails'                           => ['module' => 'Finanzas', 'model' => PaymentEmail::class, 'conflict_keys' => []],
        'payment_promises'                         => ['module' => 'Finanzas', 'model' => PaymentPromise::class, 'conflict_keys' => []],
        'payments'                                 => ['module' => 'Finanzas', 'model' => Payment::class, 'conflict_keys' => ['folio', 'client_id']],
        'payments_details'                         => ['module' => 'Finanzas', 'model' => PaymentDetail::class, 'conflict_keys' => []],
        'payments_sellers'                         => ['module' => 'Finanzas', 'model' => PaymentSeller::class, 'conflict_keys' => []],
        'plan_type_billings'                       => ['module' => 'Finanzas', 'mode' => 'raw', 'conflict_keys' => []],
        'proforma_invoice_emails'                  => ['module' => 'Finanzas', 'model' => ProformaInvoiceEmail::class, 'conflict_keys' => []],
        'receipts'                                 => ['module' => 'Finanzas', 'model' => Receipt::class, 'conflict_keys' => []],
        'taxes'                                    => ['module' => 'Finanzas', 'model' => Tax::class, 'conflict_keys' => []],
        'transactions'                             => ['module' => 'Finanzas', 'model' => Transaction::class, 'conflict_keys' => []],
        'transactions_sellers'                     => ['module' => 'Finanzas', 'model' => TransactionSeller::class, 'conflict_keys' => []],
        'type_billings'                            => ['module' => 'Finanzas', 'model' => TypeBilling::class, 'conflict_keys' => []],

        // ─── Geografía ─── (10)
        'box_zones'                                => ['module' => 'Geografía', 'model' => BoxZone::class, 'conflict_keys' => []],
        'colonies'                                 => ['module' => 'Geografía', 'model' => Colony::class, 'conflict_keys' => []],
        'districts'                                => ['module' => 'Geografía', 'model' => District::class, 'conflict_keys' => []],
        'ifts'                                     => ['module' => 'Geografía', 'model' => Ift::class, 'conflict_keys' => []],
        'locations'                                => ['module' => 'Geografía', 'model' => Location::class, 'conflict_keys' => []],
        'municipalities'                           => ['module' => 'Geografía', 'model' => Municipality::class, 'conflict_keys' => []],
        'nomenclatures'                            => ['module' => 'Geografía', 'model' => Nomenclature::class, 'conflict_keys' => ['name']],
        'states'                                   => ['module' => 'Geografía', 'model' => State::class, 'conflict_keys' => []],
        'store_zones'                              => ['module' => 'Geografía', 'model' => StoreZone::class, 'conflict_keys' => []],
        'zones'                                    => ['module' => 'Geografía', 'model' => Zone::class, 'conflict_keys' => []],

        // ─── IA ─── (12)
        'ia_chat_conversations'                    => ['module' => 'IA', 'mode' => 'raw', 'conflict_keys' => []],
        'ia_conversaciones'                        => ['module' => 'IA', 'model' => IAConversacion::class, 'conflict_keys' => []],
        'ia_memoria_proyecto'                      => ['module' => 'IA', 'model' => IAMemoriaProyecto::class, 'conflict_keys' => []],
        'ia_mensajes'                              => ['module' => 'IA', 'model' => IAMensaje::class, 'conflict_keys' => []],
        'ia_message_files'                         => ['module' => 'IA', 'model' => IAMessageFile::class, 'conflict_keys' => []],
        'ia_notas_proyecto'                        => ['module' => 'IA', 'model' => IANotaProyecto::class, 'conflict_keys' => []],
        'ia_prompts_usuario'                       => ['module' => 'IA', 'model' => IAPromptUsuario::class, 'conflict_keys' => []],
        'ia_proveedores'                           => ['module' => 'IA', 'model' => IAProveedor::class, 'conflict_keys' => []],
        'ia_proyectos'                             => ['module' => 'IA', 'model' => IAProyecto::class, 'conflict_keys' => []],
        'ia_sesiones_trabajo'                      => ['module' => 'IA', 'model' => IASesionTrabajo::class, 'conflict_keys' => []],
        'ia_tareas'                                => ['module' => 'IA', 'model' => IATarea::class, 'conflict_keys' => []],
        'ia_uso_tokens'                            => ['module' => 'IA', 'model' => IAUsoToken::class, 'conflict_keys' => []],

        // ─── Inventario ─── (10)
        'brands'                                   => ['module' => 'Inventario', 'model' => Brand::class, 'conflict_keys' => []],
        'inventory_item_custom_models'             => ['module' => 'Inventario', 'model' => InventoryItemCustomModel::class, 'conflict_keys' => []],
        'inventory_item_media'                     => ['module' => 'Inventario', 'model' => InventoryItemMedia::class, 'conflict_keys' => []],
        'inventory_item_stocks'                    => ['module' => 'Inventario', 'model' => InventoryItemStock::class, 'conflict_keys' => []],
        'inventory_item_store_zones'               => ['module' => 'Inventario', 'model' => InventoryItemStoreZone::class, 'conflict_keys' => []],
        'inventory_item_types'                     => ['module' => 'Inventario', 'model' => InventoryItemType::class, 'conflict_keys' => []],
        'inventory_items'                          => ['module' => 'Inventario', 'model' => InventoryItem::class, 'conflict_keys' => ['serial', 'sku']],
        'inventory_movements'                      => ['module' => 'Inventario', 'model' => InventoryMovement::class, 'conflict_keys' => []],
        'inventory_reservations'                   => ['module' => 'Inventario', 'model' => InventoryReservation::class, 'conflict_keys' => []],
        'inventory_stores'                         => ['module' => 'Inventario', 'model' => InventoryStore::class, 'conflict_keys' => []],

        // ─── Mapas ─── (12)
        'map_devices'                              => ['module' => 'Mapas', 'model' => MapDevice::class, 'conflict_keys' => []],
        'map_devices_ports'                        => ['module' => 'Mapas', 'model' => MapDevicePort::class, 'conflict_keys' => []],
        'map_devices_ports_connections'            => ['module' => 'Mapas', 'model' => MapDevicePortConnection::class, 'conflict_keys' => []],
        'map_fibers'                               => ['module' => 'Mapas', 'model' => MapFiber::class, 'conflict_keys' => []],
        'map_fibers_cut'                           => ['module' => 'Mapas', 'model' => MapCutFiber::class, 'conflict_keys' => []],
        'map_layers'                               => ['module' => 'Mapas', 'model' => MapLayer::class, 'conflict_keys' => []],
        'map_layers_routes'                        => ['module' => 'Mapas', 'model' => MapLayerRoute::class, 'conflict_keys' => []],
        'map_links'                                => ['module' => 'Mapas', 'model' => MapLink::class, 'conflict_keys' => []],
        'map_ports'                                => ['module' => 'Mapas', 'model' => MapPort::class, 'conflict_keys' => []],
        'map_proyects'                             => ['module' => 'Mapas', 'model' => MapProyect::class, 'conflict_keys' => []],
        'map_routes'                               => ['module' => 'Mapas', 'model' => MapRoute::class, 'conflict_keys' => []],
        'system_map_credentials'                   => ['module' => 'Mapas', 'model' => MapCredential::class, 'conflict_keys' => []],

        // ─── MegaFamilia ─── (18)
        'app_versions'                             => ['module' => 'MegaFamilia', 'model' => AppVersion::class, 'conflict_keys' => []],
        'parental_accounts'                        => ['module' => 'MegaFamilia', 'model' => ParentalAccount::class, 'conflict_keys' => []],
        'parental_alerts'                          => ['module' => 'MegaFamilia', 'model' => ParentalAlert::class, 'conflict_keys' => []],
        'parental_app_blocks'                      => ['module' => 'MegaFamilia', 'model' => ParentalAppBlock::class, 'conflict_keys' => ['profile_id', 'package_name']],
        'parental_consents'                        => ['module' => 'MegaFamilia', 'model' => ParentalConsent::class, 'conflict_keys' => ['version_number']],
        'parental_devices'                         => ['module' => 'MegaFamilia', 'model' => ParentalDevice::class, 'conflict_keys' => ['link_token']],
        'parental_events'                          => ['module' => 'MegaFamilia', 'model' => ParentalEvent::class, 'conflict_keys' => []],
        'parental_geofences'                       => ['module' => 'MegaFamilia', 'model' => ParentalGeofence::class, 'conflict_keys' => []],
        'parental_licenses'                        => ['module' => 'MegaFamilia', 'model' => ParentalLicense::class, 'conflict_keys' => []],
        'parental_locations'                       => ['module' => 'MegaFamilia', 'model' => ParentalLocation::class, 'conflict_keys' => []],
        'parental_plans'                           => ['module' => 'MegaFamilia', 'model' => ParentalPlan::class, 'conflict_keys' => ['slug']],
        'parental_profiles'                        => ['module' => 'MegaFamilia', 'model' => ParentalProfile::class, 'conflict_keys' => []],
        'parental_requests'                        => ['module' => 'MegaFamilia', 'model' => ParentalRequest::class, 'conflict_keys' => []],
        'parental_rewards'                         => ['module' => 'MegaFamilia', 'model' => ParentalReward::class, 'conflict_keys' => []],
        'parental_rules'                           => ['module' => 'MegaFamilia', 'model' => ParentalRule::class, 'conflict_keys' => ['profile_id']],
        'parental_schedules'                       => ['module' => 'MegaFamilia', 'model' => ParentalSchedule::class, 'conflict_keys' => []],
        'parental_tasks'                           => ['module' => 'MegaFamilia', 'model' => ParentalTask::class, 'conflict_keys' => []],
        'parental_web_blocks'                      => ['module' => 'MegaFamilia', 'model' => ParentalWebBlock::class, 'conflict_keys' => ['profile_id', 'domain']],

        // ─── MikroTik ─── (9)
        'mikrotik_client_hostpot_radius'           => ['module' => 'MikroTik', 'model' => MikrotikClientHostpotRadius::class, 'conflict_keys' => []],
        'mikrotik_client_hostpot_users'            => ['module' => 'MikroTik', 'model' => MikrotikClientHostpotUser::class, 'conflict_keys' => []],
        'mikrotik_client_ppoes'                    => ['module' => 'MikroTik', 'model' => MikrotikClientPpoe::class, 'conflict_keys' => []],
        'mikrotik_configs'                         => ['module' => 'MikroTik', 'model' => MikrotikConfig::class, 'conflict_keys' => []],
        'mikrotik_item_to_excecute_actions'        => ['module' => 'MikroTik', 'model' => MikrotikItemToExcecuteAction::class, 'conflict_keys' => []],
        'mikrotik_notifications'                   => ['module' => 'MikroTik', 'model' => MikrotikNotification::class, 'conflict_keys' => []],
        'mikrotik_tariff_main_tails'               => ['module' => 'MikroTik', 'model' => MikrotikTariffMainTail::class, 'conflict_keys' => []],
        'mikrotik_tariff_target_tails'             => ['module' => 'MikroTik', 'model' => MikrotikTariffTargetTail::class, 'conflict_keys' => []],
        'mikrotiks'                                => ['module' => 'MikroTik', 'model' => Mikrotik::class, 'conflict_keys' => []],

        // ─── OLT ─── (13)
        'olt_billings'                             => ['module' => 'OLT', 'model' => OltBilling::class, 'conflict_keys' => []],
        'olt_cards'                                => ['module' => 'OLT', 'model' => OltCard::class, 'conflict_keys' => ['olt_id', 'slot']],
        'olt_interruption_pons'                    => ['module' => 'OLT', 'model' => OltInterruptionPon::class, 'conflict_keys' => ['olt_id', 'board', 'port']],
        'olt_odbs'                                 => ['module' => 'OLT', 'model' => OltOdb::class, 'conflict_keys' => []],
        'olt_onus'                                 => ['module' => 'OLT', 'model' => OltOnu::class, 'conflict_keys' => ['sn', 'olt_id']],
        'olt_pon_ports'                            => ['module' => 'OLT', 'model' => OltPonPort::class, 'conflict_keys' => ['olt_id', 'board', 'pon_port']],
        'olt_speed_profiles'                       => ['module' => 'OLT', 'model' => OltSpeedProfile::class, 'conflict_keys' => []],
        'olt_type_onus'                            => ['module' => 'OLT', 'model' => OltTypeONU::class, 'conflict_keys' => ['name']],
        'olt_unconfigured_onus'                    => ['module' => 'OLT', 'model' => OltUnconfiguredOnu::class, 'conflict_keys' => ['sn']],
        'olt_uplink_ports'                         => ['module' => 'OLT', 'model' => OltUplinkPort::class, 'conflict_keys' => ['olt_id', 'name']],
        'olt_vlans'                                => ['module' => 'OLT', 'model' => OltVlan::class, 'conflict_keys' => []],
        'olt_zones'                                => ['module' => 'OLT', 'model' => OltZone::class, 'conflict_keys' => ['name']],
        'olts'                                     => ['module' => 'OLT', 'model' => Olt::class, 'conflict_keys' => []],

        // ─── Planes ─── (7)
        'bundles'                                  => ['module' => 'Planes', 'model' => Bundle::class, 'conflict_keys' => ['title']],
        'customs'                                  => ['module' => 'Planes', 'model' => Custom::class, 'conflict_keys' => ['title']],
        'duration_contracts'                       => ['module' => 'Planes', 'model' => DurationContract::class, 'conflict_keys' => []],
        'internets'                                => ['module' => 'Planes', 'model' => Internet::class, 'conflict_keys' => ['title']],
        'packages'                                 => ['module' => 'Planes', 'model' => Package::class, 'conflict_keys' => ['title']],
        'plan_bundles'                             => ['module' => 'Planes', 'mode' => 'raw', 'conflict_keys' => []],
        'plan_custom_client'                       => ['module' => 'Planes', 'mode' => 'raw', 'conflict_keys' => []],

        // ─── Proyectos ─── (2)
        'project_types'                            => ['module' => 'Proyectos', 'model' => ProjectType::class, 'conflict_keys' => []],
        'projects'                                 => ['module' => 'Proyectos', 'model' => Project::class, 'conflict_keys' => []],

        // ─── Red ─── (29)
        'active_equipment_peripherals'             => ['module' => 'Red', 'model' => ActiveEquipmentPeripheral::class, 'conflict_keys' => []],
        'active_equipment_types'                   => ['module' => 'Red', 'model' => ActiveEquipmentType::class, 'conflict_keys' => []],
        'active_equipments'                        => ['module' => 'Red', 'model' => ActiveEquipment::class, 'conflict_keys' => []],
        'buffers'                                  => ['module' => 'Red', 'model' => Buffer::class, 'conflict_keys' => ['color_id']],
        'cards'                                    => ['module' => 'Red', 'model' => Card::class, 'conflict_keys' => []],
        'cut_fibers'                               => ['module' => 'Red', 'model' => CutFiber::class, 'conflict_keys' => []],
        'equipment_links'                          => ['module' => 'Red', 'model' => EquipmentLink::class, 'conflict_keys' => []],
        'fibers'                                   => ['module' => 'Red', 'model' => Fiber::class, 'conflict_keys' => []],
        'modems'                                   => ['module' => 'Red', 'model' => Modem::class, 'conflict_keys' => []],
        'network_ips'                              => ['module' => 'Red', 'model' => NetworkIp::class, 'conflict_keys' => []],
        'networks'                                 => ['module' => 'Red', 'model' => Network::class, 'conflict_keys' => ['network']],
        'passive_equipment_types'                  => ['module' => 'Red', 'model' => PassiveEquipmentType::class, 'conflict_keys' => []],
        'passive_equipments'                       => ['module' => 'Red', 'model' => PassiveEquipment::class, 'conflict_keys' => []],
        'point_accessories'                        => ['module' => 'Red', 'model' => PointAccessory::class, 'conflict_keys' => []],
        'points'                                   => ['module' => 'Red', 'model' => Point::class, 'conflict_keys' => []],
        'pole_accessories'                         => ['module' => 'Red', 'model' => PoleAccessory::class, 'conflict_keys' => []],
        'poles'                                    => ['module' => 'Red', 'model' => Pole::class, 'conflict_keys' => []],
        'ports'                                    => ['module' => 'Red', 'model' => Port::class, 'conflict_keys' => []],
        'racks'                                    => ['module' => 'Red', 'model' => Rack::class, 'conflict_keys' => []],
        'routers'                                  => ['module' => 'Red', 'model' => Router::class, 'conflict_keys' => ['ip', 'title']],
        'service_in_address_lists'                 => ['module' => 'Red', 'model' => ServiceInAddressList::class, 'conflict_keys' => []],
        'sites'                                    => ['module' => 'Red', 'model' => Site::class, 'conflict_keys' => []],
        'splitters'                                => ['module' => 'Red', 'model' => Splitter::class, 'conflict_keys' => []],
        'transceivers'                             => ['module' => 'Red', 'model' => Transceiver::class, 'conflict_keys' => []],
        'trays'                                    => ['module' => 'Red', 'model' => Tray::class, 'conflict_keys' => []],
        'trenche_types'                            => ['module' => 'Red', 'model' => TrencheTypes::class, 'conflict_keys' => []],
        'trenches'                                 => ['module' => 'Red', 'model' => Trench::class, 'conflict_keys' => []],
        'tube_types'                               => ['module' => 'Red', 'model' => TubeType::class, 'conflict_keys' => []],
        'tubes'                                    => ['module' => 'Red', 'model' => Tube::class, 'conflict_keys' => []],

        // ─── Sistema ─── (24)
        'app_layout_configurations'                => ['module' => 'Sistema', 'model' => AppLayoutConfiguration::class, 'conflict_keys' => []],
        'column_datatable_modules'                 => ['module' => 'Sistema', 'model' => ColumnDatatableModule::class, 'conflict_keys' => []],
        'command_configs'                          => ['module' => 'Sistema', 'model' => CommandConfig::class, 'conflict_keys' => []],
        'company_information'                      => ['module' => 'Sistema', 'model' => CompanyInformation::class, 'conflict_keys' => []],
        'crud_packages'                            => ['module' => 'Sistema', 'mode' => 'raw', 'conflict_keys' => []],
        'default_values'                           => ['module' => 'Sistema', 'model' => DefaultValue::class, 'conflict_keys' => []],
        'field_modules'                            => ['module' => 'Sistema', 'model' => FieldModule::class, 'conflict_keys' => []],
        'field_types'                              => ['module' => 'Sistema', 'model' => FieldType::class, 'conflict_keys' => []],
        'general_configuration_rule'               => ['module' => 'Sistema', 'model' => GeneralConfigurationRule::class, 'conflict_keys' => []],
        'history_general_configuration_rule'       => ['module' => 'Sistema', 'model' => HistoryGeneralConfigurationRule::class, 'conflict_keys' => []],
        'module_registry'                          => ['module' => 'Sistema', 'model' => ModuleRegistry::class, 'conflict_keys' => ['slug']],
        'modules'                                  => ['module' => 'Sistema', 'model' => Module::class, 'conflict_keys' => []],
        'release_descriptions'                     => ['module' => 'Sistema', 'model' => ReleaseDescription::class, 'conflict_keys' => []],
        'releases'                                 => ['module' => 'Sistema', 'model' => Release::class, 'conflict_keys' => ['version']],
        'setting_debt_payment_client_customs'      => ['module' => 'Sistema', 'model' => SettingDebtPaymentClientCustom::class, 'conflict_keys' => []],
        'setting_debt_payment_client_recurrents'   => ['module' => 'Sistema', 'model' => SettingDebtPaymentClientRecurrent::class, 'conflict_keys' => []],
        'setting_imports'                          => ['module' => 'Sistema', 'model' => SettingToolsImport::class, 'conflict_keys' => []],
        'setting_table'                            => ['module' => 'Sistema', 'mode' => 'raw', 'conflict_keys' => []],
        'setting_tables'                           => ['module' => 'Sistema', 'model' => SettingTable::class, 'conflict_keys' => []],
        'sucursals'                                => ['module' => 'Sistema', 'model' => Sucursal::class, 'conflict_keys' => ['email']],
        'system_settings'                          => ['module' => 'Sistema', 'mode' => 'raw', 'conflict_keys' => ['key']],
        'tables'                                   => ['module' => 'Sistema', 'model' => Table::class, 'conflict_keys' => []],
        'user_column_datatable_modules'            => ['module' => 'Sistema', 'model' => UserColumnDatatableModule::class, 'conflict_keys' => []],
        'user_column_dt_expand'                    => ['module' => 'Sistema', 'model' => UserColumnDatatableExpand::class, 'conflict_keys' => []],

        // ─── Tareas ─── (12)
        'frequency_commands'                       => ['module' => 'Tareas', 'model' => FrequencyCommand::class, 'conflict_keys' => []],
        'frequency_estimated_dedicated_times'      => ['module' => 'Tareas', 'model' => FrequencyEstimatedDedicatedTime::class, 'conflict_keys' => []],
        'list_template_verifications'              => ['module' => 'Tareas', 'model' => ListTemplateVerification::class, 'conflict_keys' => []],
        'list_template_verifications_tasks'        => ['module' => 'Tareas', 'model' => ListTemplateVerificationTask::class, 'conflict_keys' => []],
        'observation_tasks'                        => ['module' => 'Tareas', 'model' => ObservationTask::class, 'conflict_keys' => []],
        'reminders'                                => ['module' => 'Tareas', 'model' => Reminder::class, 'conflict_keys' => []],
        'reminders_configurations'                 => ['module' => 'Tareas', 'model' => RemindersConfiguration::class, 'conflict_keys' => []],
        'task_notifications'                       => ['module' => 'Tareas', 'model' => TaskNotification::class, 'conflict_keys' => []],
        'tasks'                                    => ['module' => 'Tareas', 'model' => Task::class, 'conflict_keys' => []],
        'template_tasks'                           => ['module' => 'Tareas', 'model' => TemplateTask::class, 'conflict_keys' => []],
        'template_verifications'                   => ['module' => 'Tareas', 'model' => TemplateVerification::class, 'conflict_keys' => []],
        'work_flows'                               => ['module' => 'Tareas', 'model' => WorkFlow::class, 'conflict_keys' => []],

        // ─── Tickets ─── (2)
        'ticket_threads'                           => ['module' => 'Tickets', 'model' => TicketThread::class, 'conflict_keys' => []],
        'tickets'                                  => ['module' => 'Tickets', 'model' => Ticket::class, 'conflict_keys' => ['folio']],

        // ─── Usuarios ─── (1)
        'users'                                    => ['module' => 'Usuarios', 'model' => User::class, 'conflict_keys' => ['email']],

        // ─── Vendedores ─── (16)
        'commissions'                              => ['module' => 'Vendedores', 'model' => Commission::class, 'conflict_keys' => []],
        'commissions_details'                      => ['module' => 'Vendedores', 'model' => CommissionDetail::class, 'conflict_keys' => []],
        'commissions_rules'                        => ['module' => 'Vendedores', 'model' => CommissionRule::class, 'conflict_keys' => []],
        'commissions_rules_sellers'                => ['module' => 'Vendedores', 'mode' => 'raw', 'conflict_keys' => []],
        'distribution_commission_sales'            => ['module' => 'Vendedores', 'model' => DistributionCommission::class, 'conflict_keys' => []],
        'distribution_commission_sales_amount'     => ['module' => 'Vendedores', 'model' => DistributionCommissionAmount::class, 'conflict_keys' => []],
        'history_sellers_rules'                    => ['module' => 'Vendedores', 'model' => HistorySellerRule::class, 'conflict_keys' => []],
        'medium_sales'                             => ['module' => 'Vendedores', 'model' => MediumOfSale::class, 'conflict_keys' => []],
        'partner_module'                           => ['module' => 'Vendedores', 'mode' => 'raw', 'conflict_keys' => []],
        'partners'                                 => ['module' => 'Vendedores', 'model' => Partner::class, 'conflict_keys' => []],
        'prospects'                                => ['module' => 'Vendedores', 'mode' => 'raw', 'conflict_keys' => []],
        'ranges_of_sales_sectors'                  => ['module' => 'Vendedores', 'model' => RangeSale::class, 'conflict_keys' => ['sector', 'range']],
        'sales'                                    => ['module' => 'Vendedores', 'mode' => 'raw', 'conflict_keys' => []],
        'seller_status'                            => ['module' => 'Vendedores', 'model' => SellerStatus::class, 'conflict_keys' => []],
        'seller_types'                             => ['module' => 'Vendedores', 'model' => SellerType::class, 'conflict_keys' => []],
        'sellers'                                  => ['module' => 'Vendedores', 'model' => Seller::class, 'conflict_keys' => ['email', 'phone']],

        // ─── Voz ─── (1)
        'voises'                                   => ['module' => 'Voz', 'model' => Voise::class, 'conflict_keys' => []],

        // ─── WhatsApp ─── (1)
        'whatsapp_instances'                       => ['module' => 'WhatsApp', 'model' => WhatsAppInstance::class, 'conflict_keys' => ['slug']],
    ];

    /** Carpeta temporal donde se persiste el archivo durante el flujo. */
    public const STORAGE_DIR = 'app/smart_import';

    public function analyzeFile(UploadedFile $file): array
    {
        $dir = storage_path(self::STORAGE_DIR);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0775, true, true);
        }

        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storedName = $token . '.' . $extension;
        $file->move($dir, $storedName);
        $path = $dir . '/' . $storedName;

        $this->resetAnalysisState();

        $format = $this->detectFormat($extension, $path);
        $datasets = match ($format) {
            'sql'   => $this->parseSql($path),
            'zip'   => $this->parseZip($path),
            default => [],
        };

        $report = $this->buildAnalysisReport($datasets);

        $totalRows = !empty($this->lastRowCounts)
            ? array_sum($this->lastRowCounts)
            : array_sum(array_map('count', $datasets));

        return [
            'token'        => $token,
            'file'         => $storedName,
            'format'       => $format,
            'datasets'     => $datasets,
            'dump_columns' => $this->dumpColumnsByTable, // pobladas por parseSql → handle schema drift
            'source_tables'=> $this->sourceTableSchemas,
            'report'       => $this->sanitizeUtf8($report),
            'total_rows'   => $totalRows,
        ];
    }

    public function loadDatasetsForExecution(array $analysis): array
    {
        $storedName = $analysis['file'] ?? null;
        $format = $analysis['format'] ?? null;
        if (!$storedName || !$format) {
            return $analysis['datasets'] ?? [];
        }

        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (!File::exists($path)) {
            return $analysis['datasets'] ?? [];
        }

        $this->resetAnalysisState();
        $this->dumpColumnsByTable = $analysis['dump_columns'] ?? [];
        $this->sourceTableSchemas = $analysis['source_tables'] ?? [];

        return match ($format) {
            'sql'   => $this->parseSql($path, PHP_INT_MAX),
            'zip'   => $this->parseZip($path, PHP_INT_MAX),
            default => $analysis['datasets'] ?? [],
        };
    }

    public function executeSqlImportFromAnalysis(array $analysis, array $options = [], ?callable $onTableStarted = null): array
    {
        $storedName = $analysis['file'] ?? null;
        if (!$storedName) {
            return [];
        }

        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (!File::exists($path)) {
            return [];
        }

        $this->sourceTableSchemas = $analysis['source_tables'] ?? $this->sourceTableSchemas;
        $plan = $this->buildExecutionPlan($analysis, $options);
        $summary = $this->initializeExecutionSummary($plan);
        $rowIndexes = [];
        $startedTables = [];

        $this->withForeignKeyChecksDisabled(function () use ($path, &$plan, &$summary, &$rowIndexes, &$startedTables, $onTableStarted): void {
            $this->preparePlannedTables($plan, $summary);
            $this->executeSqlImportFromPath($path, $plan, $summary, $rowIndexes, $startedTables, $onTableStarted);
        });

        $this->notifyEmptyPlannedTables($plan, $startedTables, $onTableStarted);
        $this->repairAutoIncrementsForSummary($plan, $summary);
        $this->auditForeignKeysForSummary($plan, $summary);

        return $summary;
    }

    public function executeZipImportFromAnalysis(array $analysis, array $options = [], ?callable $onTableStarted = null): array
    {
        $storedName = $analysis['file'] ?? null;
        if (!$storedName) {
            return [];
        }

        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (!File::exists($path)) {
            return [];
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip no está disponible en este servidor.');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP (código ' . $opened . ').');
        }

        $extractDir = storage_path(self::STORAGE_DIR . '/execute-extracted-' . Str::uuid());
        if (!File::exists($extractDir)) {
            File::makeDirectory($extractDir, 0775, true, true);
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $this->sourceTableSchemas = $analysis['source_tables'] ?? $this->sourceTableSchemas;
        $plan = $this->buildExecutionPlan($analysis, $options);
        $summary = $this->initializeExecutionSummary($plan);
        $rowIndexes = [];
        $startedTables = [];

        try {
            $this->withForeignKeyChecksDisabled(function () use ($extractDir, &$plan, &$summary, &$rowIndexes, &$startedTables, $onTableStarted): void {
                $this->preparePlannedTables($plan, $summary);

                foreach (File::allFiles($extractDir) as $extracted) {
                    $ext = strtolower($extracted->getExtension());
                    $pathname = $extracted->getPathname();

                    if ($ext !== 'sql') {
                        continue;
                    }

                    $this->executeSqlImportFromPath($pathname, $plan, $summary, $rowIndexes, $startedTables, $onTableStarted);
                }
            });
        } finally {
            File::deleteDirectory($extractDir);
        }

        $this->notifyEmptyPlannedTables($plan, $startedTables, $onTableStarted);
        $this->repairAutoIncrementsForSummary($plan, $summary);
        $this->auditForeignKeysForSummary($plan, $summary);

        return $summary;
    }

    public function detectConflicts(array $datasets, array $options = []): array
    {
        $conflicts = [];
        $stats = [];
        $detailLimit = max(1, (int) ($options['detail_limit_per_table'] ?? self::PREVIEW_CONFLICT_DETAIL_LIMIT));
        $chunkSize = max(1, (int) ($options['chunk_size'] ?? self::PREVIEW_CONFLICT_CHUNK_SIZE));

        foreach ($datasets as $table => $rows) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $info = $this->importDescriptor($table);
            $keys = $info['conflict_keys'];
            if (empty($keys)) continue;

            $tableConflicts = [];
            $conflictCount = 0;
            $scannedRows = count($rows);

            foreach (array_chunk($rows, $chunkSize, true) as $chunkRows) {
                $existingMap = $this->batchFindExisting($table, $keys, $chunkRows);

                foreach ($chunkRows as $index => $row) {
                    $existing = $existingMap[$index] ?? null;
                    if (!$existing) {
                        continue;
                    }

                    $conflictCount++;
                    if (count($tableConflicts) < $detailLimit) {
                        $tableConflicts[] = [
                            'index'    => $index,
                            'incoming' => $row,
                            'existing' => $existing,
                            'matched'  => $this->matchedKeys($keys, $row, $existing),
                        ];
                    }
                }
            }

            if ($conflictCount > 0) {
                $conflicts[$table] = $tableConflicts;
                $stats[$table] = [
                    'total_conflicts'    => $conflictCount,
                    'returned_conflicts' => count($tableConflicts),
                    'truncated'          => $conflictCount > count($tableConflicts),
                    'scanned_rows'       => $scannedRows,
                    'detail_limit'       => $detailLimit,
                ];
            }
        }

        return [
            'items' => $conflicts,
            'stats' => $stats,
            'meta'  => [
                'detail_limit_per_table' => $detailLimit,
                'chunk_size'             => $chunkSize,
                'sampled_response'       => true,
            ],
        ];
    }

    public function resolveWithAI(array $conflicts): array
    {
        $proveedor = IAProveedor::where('activo', true)->orderBy('id')->first();
        if (!$proveedor) {
            return ['error' => 'No hay proveedor de IA activo. Configure uno en /ia/configuracion.'];
        }

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
        } catch (Throwable $e) {
            return ['error' => 'No se pudo iniciar el adaptador IA: ' . $e->getMessage()];
        }

        $resultados = [];
        foreach ($conflicts as $table => $items) {
            foreach ($items as $item) {
                $prompt = $this->buildConflictPrompt($table, $item);
                try {
                    $respuesta = $adaptador->enviarMensaje([], $prompt, [], 'Eres un asistente experto en migración de datos.');
                    $resultados[$table][$item['index']] = $this->parseAIRecommendation($respuesta['texto'] ?? '');
                } catch (Throwable $e) {
                    Log::warning('SmartImport IA: ' . $e->getMessage());
                    $resultados[$table][$item['index']] = [
                        'accion' => 'omitir',
                        'razon'  => 'Sin respuesta de IA (' . $e->getMessage() . '). Se sugiere omitir por seguridad.',
                    ];
                }
            }
        }
        return $resultados;
    }

    public function executeImport(array $datasets, array $options = []): array
    {
        $summary = [];
        foreach ($datasets as $table => $rows) {
            $info = $this->importDescriptor($table);
            $skipReason = $this->validateMapEntry($table, $info);
            if ($skipReason !== null) {
                $summary[$table] = ['imported' => 0, 'skipped' => count($rows), 'errors' => 0, 'reason' => $skipReason];
                continue;
            }

            $defaultAction = $options[$table]['action'] ?? 'replace';
            $perRow = $options[$table]['conflicts'] ?? [];
            $pk = $this->pkOf($table, $info);
            $imported = 0;
            $skipped = 0;
            $errors = 0;

            $conflictRows = $this->prepareRowsForConflictDetection($table, $rows);
            $existingMap = $this->batchFindExisting($table, $info['conflict_keys'], $conflictRows);
            if ($this->canUseBulkPath($info)) {
                $summary[$table] = $this->executeBulkImportTable(
                    $table,
                    $info,
                    $rows,
                    $existingMap,
                    $defaultAction,
                    $perRow
                );
                continue;
            }

            foreach ($rows as $index => $row) {
                try {
                    $existing = $existingMap[$index] ?? null;
                    $action = $existing ? ($perRow[$index] ?? $defaultAction) : 'insert';

                    if ($action === 'skip') {
                        $skipped++;
                        continue;
                    }
                    if ($action === 'replace' && $existing) {
                        if (isset($existing[$pk]) && $this->hasColumnCached($table, $pk)) {
                            $this->updateRow($table, $info, $existing[$pk], $row);
                        } else {
                            $this->updateExistingRowByConflictKeys($table, $info['conflict_keys'], $row);
                        }
                        $imported++;
                        continue;
                    }
                    if ($action === 'duplicate') {
                        unset($row[$pk]);
                    }
                    $this->insertRow($table, $info, $row);
                    $imported++;
                } catch (Throwable $e) {
                    Log::warning('SmartImport row error [' . $table . ']: ' . $e->getMessage());
                    $errors++;
                }
            }
            $summary[$table] = compact('imported', 'skipped', 'errors');
        }
        return $summary;
    }

    private function updateExistingRowByConflictKeys(string $table, array $keys, array $row): void
    {
        if ($keys === []) {
            throw new \RuntimeException('No hay llaves de conflicto disponibles para actualizar ' . $table . '.');
        }

        $clean = $this->prepareDirectWriteRow($table, $row, isUpdate: true);
        if ($clean === []) {
            throw new \RuntimeException('Fila vacía después de normalizar columnas para ' . $table . '.');
        }

        $query = DB::table($table);
        foreach ($keys as $key) {
            if (!$this->hasConflictValue($clean, $key)) {
                throw new \RuntimeException('Falta llave de conflicto ' . $key . ' para actualizar ' . $table . '.');
            }
            $query->where($key, $clean[$key]);
        }

        $update = $clean;
        foreach ($keys as $key) {
            unset($update[$key]);
        }

        if ($update !== []) {
            $query->update($update);
        }
    }

    public function cleanup(string $storedName): void
    {
        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /* ===================== Helpers privados ===================== */

    private function buildExecutionPlan(array $analysis, array $options): array
    {
        $globalMode = $this->normalizeGlobalMode($options['global_mode'] ?? self::GLOBAL_MODE_SMART);
        $tables = array_values(array_unique(array_merge(
            array_column($analysis['report'] ?? [], 'table'),
            array_keys($analysis['source_tables'] ?? [])
        )));
        $orderedTables = $this->topologicalSortTables($tables);
        $rawTableModes = is_array($options['table_modes'] ?? null) ? $options['table_modes'] : [];
        $planTables = [];

        foreach ($orderedTables as $table) {
            $rawMode = $rawTableModes[$table]['mode']
                ?? $rawTableModes[$table]
                ?? $options[$table]['mode']
                ?? null;
            $mode = $this->normalizeGlobalMode($rawMode ?? $globalMode);

            $planTables[$table] = [
                'table'                => $table,
                'mode'                 => $mode,
                'info'                 => $this->importDescriptor($table),
                'source_schema'        => $analysis['source_tables'][$table] ?? $this->sourceTableSchemas[$table] ?? null,
                'target_exists_before' => Schema::hasTable($table),
                'disabled'             => false,
            ];
        }

        return [
            'global_mode'   => $globalMode,
            'ordered_tables'=> $orderedTables,
            'tables'        => $planTables,
        ];
    }

    private function normalizeGlobalMode(?string $mode): string
    {
        return match ($mode) {
            self::GLOBAL_MODE_FORCE_SOURCE,
            self::GLOBAL_MODE_SKIP_EXISTING,
            self::GLOBAL_MODE_SMART => $mode,
            default => self::GLOBAL_MODE_SMART,
        };
    }

    private function initializeExecutionSummary(array $plan): array
    {
        $summary = [];
        foreach ($plan['ordered_tables'] as $table) {
            $mode = $plan['tables'][$table]['mode'] ?? self::GLOBAL_MODE_SMART;
            $summary[$table] = [
                'imported'             => 0,
                'skipped'              => 0,
                'errors'               => 0,
                'mode'                 => $mode,
                'created'              => false,
                'replaced'             => false,
                'added_columns'        => [],
                'auto_increment'       => null,
                'warnings'             => [],
                'target_exists_before' => (bool) ($plan['tables'][$table]['target_exists_before'] ?? false),
            ];
        }

        return $summary;
    }

    private function preparePlannedTables(array &$plan, array &$summary): void
    {
        foreach ($plan['ordered_tables'] as $table) {
            $tablePlan = &$plan['tables'][$table];
            $sourceSchema = $tablePlan['source_schema'] ?? null;
            $mode = $tablePlan['mode'] ?? self::GLOBAL_MODE_SMART;

            if (!$tablePlan['target_exists_before']) {
                if ($sourceSchema === null) {
                    $tablePlan['disabled'] = true;
                    $summary[$table]['warnings'][] = 'sin_create_table_para_crear_destino';
                    continue;
                }

                $created = $this->createTargetTableFromSource($table, $sourceSchema);
                if ($created) {
                    $summary[$table]['created'] = true;
                } else {
                    $tablePlan['disabled'] = true;
                    $summary[$table]['warnings'][] = 'no_se_pudo_crear_tabla_destino';
                    continue;
                }
            }

            if ($sourceSchema !== null && in_array($mode, [self::GLOBAL_MODE_FORCE_SOURCE, self::GLOBAL_MODE_SMART], true)) {
                $addedColumns = $this->syncMissingTargetColumns($table, $sourceSchema);
                if ($addedColumns !== []) {
                    $summary[$table]['added_columns'] = array_values(array_unique(array_merge(
                        $summary[$table]['added_columns'] ?? [],
                        $addedColumns
                    )));
                    $summary[$table]['warnings'][] = 'columnas_agregadas_desde_dump';
                }
            }
        }

        foreach (array_reverse($plan['ordered_tables']) as $table) {
            $tablePlan = $plan['tables'][$table] ?? null;
            if (!$tablePlan || ($tablePlan['disabled'] ?? false)) {
                continue;
            }
            if (($tablePlan['mode'] ?? null) !== self::GLOBAL_MODE_FORCE_SOURCE) {
                continue;
            }
            if (!($tablePlan['target_exists_before'] ?? false)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                continue;
            }

            $this->clearExistingTable($table);
            $summary[$table]['replaced'] = true;
        }
    }

    private function createTargetTableFromSource(string $table, array $sourceSchema): bool
    {
        $sql = $this->buildCreateSqlWithoutForeignKeys($table, $sourceSchema);
        if ($sql === null) {
            return false;
        }

        try {
            DB::statement($sql);
        } catch (Throwable $e) {
            Log::warning('SmartImport create table error [' . $table . ']: ' . $e->getMessage());
            return false;
        }

        unset($this->targetTableExistsCache[$table], $this->tableMetadataCache[$table], $this->hasColumnCache[$table]);

        return Schema::hasTable($table);
    }

    private function buildCreateSqlWithoutForeignKeys(string $table, array $sourceSchema): ?string
    {
        $definitions = array_values($sourceSchema['column_definitions'] ?? []);
        foreach ($sourceSchema['key_definitions'] ?? [] as $definition) {
            $definitions[] = $definition;
        }

        if ($definitions === []) {
            return null;
        }

        $tableOptions = trim((string) ($sourceSchema['table_options'] ?? ''));
        if ($tableOptions === '') {
            $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        return sprintf(
            "CREATE TABLE `%s` (\n  %s\n) %s;",
            str_replace('`', '``', $table),
            implode(",\n  ", $definitions),
            rtrim($tableOptions, ';')
        );
    }

    private function syncMissingTargetColumns(string $table, array $sourceSchema): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $addedColumns = [];
        foreach (($sourceSchema['column_definitions'] ?? []) as $column => $definition) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN %s',
                    str_replace('`', '``', $table),
                    $definition
                ));
                unset($this->tableMetadataCache[$table], $this->hasColumnCache[$table]);
                $addedColumns[] = $column;
            } catch (Throwable $e) {
                Log::warning('SmartImport add column error [' . $table . '.' . $column . ']: ' . $e->getMessage());
            }
        }

        return $addedColumns;
    }

    private function clearExistingTable(string $table): void
    {
        $this->withForeignKeyChecksDisabled(function () use ($table): void {
            try {
                DB::statement('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
            } catch (Throwable $e) {
                Log::warning('SmartImport truncate error [' . $table . ']: ' . $e->getMessage());
                DB::table($table)->delete();
            }
        });
    }

    /**
     * @template TReturn
     * @param callable():TReturn $callback
     * @return TReturn
     */
    private function withForeignKeyChecksDisabled(callable $callback): mixed
    {
        $this->disableForeignKeyChecks();
        try {
            return $callback();
        } finally {
            $this->restoreForeignKeyChecks();
        }
    }

    private function disableForeignKeyChecks(): void
    {
        if ($this->foreignKeyChecksDisabledDepth === 0) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        $this->foreignKeyChecksDisabledDepth++;
    }

    private function restoreForeignKeyChecks(): void
    {
        if ($this->foreignKeyChecksDisabledDepth <= 0) {
            $this->foreignKeyChecksDisabledDepth = 0;
            return;
        }

        $this->foreignKeyChecksDisabledDepth--;
        if ($this->foreignKeyChecksDisabledDepth === 0) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $e) {
                Log::warning('SmartImport restore FK checks error: ' . $e->getMessage());
            }
        }
    }

    private function notifyEmptyPlannedTables(array $plan, array &$startedTables, ?callable $onTableStarted): void
    {
        if (!$onTableStarted) {
            return;
        }

        foreach ($plan['ordered_tables'] as $table) {
            if (isset($startedTables[$table])) {
                continue;
            }
            $startedTables[$table] = true;
            $onTableStarted($table);
        }
    }

    private function repairAutoIncrementsForSummary(array $plan, array &$summary): void
    {
        foreach ($plan['ordered_tables'] as $table) {
            if (($plan['tables'][$table]['disabled'] ?? false) || !Schema::hasTable($table)) {
                continue;
            }

            $repair = $this->repairAutoIncrement($table);
            if ($repair === null) {
                continue;
            }

            $summary[$table]['auto_increment'] = $repair;
            if (($repair['adjusted'] ?? false) === true) {
                $summary[$table]['warnings'][] = 'auto_increment_ajustado';
            } elseif (($repair['error'] ?? null) !== null) {
                $summary[$table]['warnings'][] = 'auto_increment_no_se_pudo_ajustar';
            }
        }
    }

    /**
     * Ajusta el contador AUTO_INCREMENT luego de merges con IDs explícitos.
     * MySQL no siempre avanza el contador cuando un upsert actualiza el PK.
     *
     * @return array{column: string, max_id: int, previous_auto_increment: int|null, next_auto_increment: int, adjusted: bool, error?: string}|null
     */
    private function repairAutoIncrement(string $table): ?array
    {
        try {
            $autoColumn = DB::selectOne(
                "SELECT COLUMN_NAME AS column_name
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND EXTRA LIKE '%auto_increment%'
                 LIMIT 1",
                [$table]
            );

            $column = (string) ($autoColumn->column_name ?? '');
            if ($column === '') {
                return null;
            }

            $maxRow = DB::selectOne(sprintf(
                'SELECT MAX(%s) AS max_id FROM %s',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($table)
            ));
            $maxId = $maxRow->max_id ?? null;
            if ($maxId === null || !is_numeric($maxId)) {
                return null;
            }

            $maxId = (int) $maxId;
            $next = $maxId + 1;
            $previous = $this->currentAutoIncrementValue($table);

            if ($previous !== null && $previous > $maxId) {
                return null;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s AUTO_INCREMENT = %d',
                $this->quoteIdentifier($table),
                $next
            ));

            return [
                'column'                  => $column,
                'max_id'                  => $maxId,
                'previous_auto_increment' => $previous,
                'next_auto_increment'     => $next,
                'adjusted'                => true,
            ];
        } catch (Throwable $e) {
            Log::warning('SmartImport auto increment repair error [' . $table . ']: ' . $e->getMessage());

            return [
                'column'                  => '',
                'max_id'                  => 0,
                'previous_auto_increment' => null,
                'next_auto_increment'     => 0,
                'adjusted'                => false,
                'error'                   => $e->getMessage(),
            ];
        }
    }

    private function currentAutoIncrementValue(string $table): ?int
    {
        try {
            $create = DB::selectOne('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
            $sql = (string) ($create->{'Create Table'} ?? '');
            if ($sql !== '' && preg_match('/\bAUTO_INCREMENT=(\d+)/', $sql, $matches) === 1) {
                return (int) $matches[1];
            }
        } catch (Throwable $e) {
            Log::warning('SmartImport read auto increment error [' . $table . ']: ' . $e->getMessage());
        }

        return null;
    }

    private function auditForeignKeysForSummary(array $plan, array &$summary): void
    {
        foreach ($plan['ordered_tables'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($this->targetForeignKeyMetadata($table) as $foreignKey) {
                $audit = $this->auditForeignKey($table, $foreignKey);
                if ($audit === null || ($audit['orphaned_rows'] ?? 0) < 1) {
                    continue;
                }

                $summary[$table]['foreign_key_warnings'][] = $audit;
                $summary[$table]['warnings'][] = sprintf(
                    'fk_huerfana: %s -> %s.%s; %d registros; ejemplos: %s',
                    $audit['column'],
                    $audit['referenced_table'],
                    $audit['referenced_column'],
                    $audit['orphaned_rows'],
                    implode(', ', array_map('strval', $audit['sample_values']))
                );
            }
        }
    }

    /**
     * @param array{column: string, referenced_table: string, referenced_column: string, nullable: bool} $foreignKey
     * @return array{column: string, referenced_table: string, referenced_column: string, orphaned_rows: int, sample_values: array<int, mixed>}|null
     */
    private function auditForeignKey(string $table, array $foreignKey): ?array
    {
        $column = $foreignKey['column'] ?? '';
        $referencedTable = $foreignKey['referenced_table'] ?? '';
        $referencedColumn = $foreignKey['referenced_column'] ?? '';
        if ($column === '' || $referencedTable === '' || $referencedColumn === '') {
            return null;
        }

        if (!Schema::hasTable($referencedTable)) {
            return null;
        }

        try {
            $childTable = $this->quoteIdentifier($table);
            $parentTable = $this->quoteIdentifier($referencedTable);
            $childColumn = $this->quoteIdentifier($column);
            $parentColumn = $this->quoteIdentifier($referencedColumn);
            $where = "child.{$childColumn} IS NOT NULL AND parent.{$parentColumn} IS NULL";
            $join = "FROM {$childTable} child LEFT JOIN {$parentTable} parent ON child.{$childColumn} = parent.{$parentColumn}";

            $countRow = DB::selectOne("SELECT COUNT(*) AS aggregate {$join} WHERE {$where}");
            $count = (int) ($countRow->aggregate ?? 0);
            if ($count < 1) {
                return null;
            }

            $sampleRows = DB::select("SELECT DISTINCT child.{$childColumn} AS value {$join} WHERE {$where} LIMIT 5");
            $sampleValues = array_map(static fn (object $row) => $row->value ?? null, $sampleRows);

            return [
                'column'            => $column,
                'referenced_table'  => $referencedTable,
                'referenced_column' => $referencedColumn,
                'orphaned_rows'     => $count,
                'sample_values'     => $sampleValues,
            ];
        } catch (Throwable $e) {
            Log::warning('SmartImport FK audit error [' . $table . '.' . $column . ']: ' . $e->getMessage());
            return null;
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function executeDirectInsertTable(string $table, array $rows): array
    {
        $insertRows = [];
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $prepared = $this->prepareRawImportRow($table, $row);
                if ($prepared === []) {
                    $errors++;
                    continue;
                }
                $insertRows[] = $prepared;
            } catch (Throwable $e) {
                Log::warning('SmartImport direct insert prepare error [' . $table . ']: ' . $e->getMessage());
                $errors++;
            }
        }

        $imported = $this->flushBulkInsertRows($table, $insertRows, $errors, normalizeForeignKeys: false);

        return [
            $table => [
                'imported' => $imported,
                'skipped'  => 0,
                'errors'   => $errors,
            ],
        ];
    }

    private function detectFormat(string $extension, string $path): string
    {
        $byExt = ['sql' => 'sql', 'zip' => 'zip'];
        if (isset($byExt[$extension])) {
            return $byExt[$extension];
        }
        $head = @file_get_contents($path, false, null, 0, 512) ?: '';
        if (str_starts_with($head, "PK\x03\x04")) return 'zip';
        if (stripos($head, 'INSERT INTO') !== false || stripos($head, 'CREATE TABLE') !== false) return 'sql';
        throw new \RuntimeException('Formato no soportado. Smart Import sólo acepta .sql o .zip con dumps SQL.');
    }

    private function parseZip(string $path, ?int $sqlRowStoreLimit = self::MAX_ROWS_PER_TABLE): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip no está disponible en este servidor.');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP (código ' . $opened . ').');
        }

        $extractDir = storage_path(self::STORAGE_DIR . '/extracted-' . Str::uuid());
        if (!File::exists($extractDir)) {
            File::makeDirectory($extractDir, 0775, true, true);
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $datasets = [];
        try {
            foreach (File::allFiles($extractDir) as $extracted) {
                $ext = strtolower($extracted->getExtension());
                if ($ext !== 'sql') {
                    continue;
                }
                $sub = $this->parseSql($extracted->getPathname(), $sqlRowStoreLimit);
                foreach ($sub as $table => $rows) {
                    $datasets[$table] = array_merge($datasets[$table] ?? [], $rows);
                }
            }
        } finally {
            File::deleteDirectory($extractDir);
        }

        return $datasets;
    }

    /**
     * Reemplaza bytes inválidos UTF-8 por su equivalente convertido para evitar
     * JsonEncodingException al persistir el report en ImportExportLog->ai_analysis.
     * Pasa strings por mb_convert_encoding UTF-8→UTF-8 (descarta secuencias rotas).
     */
    private function sanitizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeUtf8($v);
            }
            return $out;
        }
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return $value;
    }

    /**
     * Parser SQL en STREAMING con FSM (finite state machine) carácter-por-carácter.
     *
     * Por qué el approach previo fallaba con dumps gigantes:
     *   El parser anterior acumulaba el statement entero en RAM hasta encontrar
     *   `;` (fin de statement). Para mysqldump `--extended-insert` con un solo
     *   INSERT de cientos de MB ("INSERT INTO t VALUES (1,'a'),(2,'b'),...;"),
     *   el buffer excedía MAX_STATEMENT_BYTES (100 MB) y se DESCARTABA completo,
     *   dejando $rowCounts en cero — exactamente el bug "report:[]" reportado.
     *
     * Solución actual:
     *   Procesar carácter por carácter manteniendo dos estados principales:
     *     - $currentTable === null  → buscando "INSERT INTO tabla VALUES"
     *     - $currentTable !== null  → dentro de VALUES, consumiendo tuplas (...)
     *   Cada tupla `(...)` cerrada se procesa y CUENTA al instante, sin esperar
     *   al `;` final. El buffer en RAM se mantiene mínimo (solo lo necesario
     *   para parsear el header del INSERT y la tupla actual).
     *
     * Cap por tabla: MAX_ROWS_PER_TABLE filas en memoria para sample/conflictos.
     * Counts exactos siguen llegando a $lastRowCounts incluso si truncamos.
     * Hard limit MAX_LINES_FOR_ANALYSIS (iteraciones del scanner) para acotar
     * tiempo de respuesta en uploads enormes; el resto del archivo queda
     * en disco para que executeImport pueda re-procesarlo.
     */
    private const MAX_ROWS_PER_TABLE       = 10000;
    private const STREAM_CHUNK_BYTES       = 65536;              // 64 KB por fread
    private const MAX_INSERT_HEADER_BYTES  = 16384;              // INSERT INTO ... VALUES rara vez excede 16 KB
    private const MAX_SCAN_BYTES           = 500 * 1024 * 1024;  // 500 MB de scan máximo (no leer archivos absurdos)

    private function parseSql(string $path, ?int $rowStoreLimit = self::MAX_ROWS_PER_TABLE): array
    {
        return $this->scanSqlFile($path, $rowStoreLimit);
    }

    private function scanSqlFile(
        string $path,
        ?int $rowStoreLimit = self::MAX_ROWS_PER_TABLE,
        ?callable $onAcceptedRow = null,
        ?int $maxScanBytes = self::MAX_SCAN_BYTES
    ): array
    {
        // Pre-pass: extraer esquema y orden de columnas desde CREATE TABLE.
        // Esto alimenta tanto el mapeo seguro de columnas como la capacidad
        // de crear tablas faltantes a partir del dump.
        foreach ($this->extractDumpTableSchemas($path) as $table => $schema) {
            $this->sourceTableSchemas[$table] = $schema;
            $this->dumpColumnsByTable[$table] = $schema['columns'];
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return [];
        }

        $datasets       = [];
        $rowCounts      = [];

        // Estado FSM
        $currentTable   = null;   // ?string — tabla del INSERT actual, null fuera de un INSERT
        $currentColumns = [];     // string[] — columnas del INSERT actual
        $skipCurrentInsert = false; // true cuando el INSERT apunta a una tabla no aplicable
        $header         = '';     // buffer mientras buscamos "INSERT INTO ... VALUES"
        $tuple          = '';     // buffer de la tupla actual cuando estamos dentro de VALUES
        $depth          = 0;      // profundidad de paréntesis (siempre 0 o 1 en SQL bien formado)
        $inString       = false;  // dentro de string '...'
        $escape         = false;  // próximo char está escapado
        $scanned        = 0;      // bytes totales escaneados (para MAX_SCAN_BYTES)

        try {
            while (!feof($handle) && ($maxScanBytes === null || $scanned < $maxScanBytes)) {
                $chunk = fread($handle, self::STREAM_CHUNK_BYTES);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $scanned += strlen($chunk);
                $chunkLen = strlen($chunk);

                for ($i = 0; $i < $chunkLen; $i++) {
                    $c = $chunk[$i];

                    if ($currentTable === null) {
                        // ─── ESTADO: BUSCANDO HEADER "INSERT INTO ... VALUES" ───
                        $header .= $c;

                        // Trim agresivo: solo nos importa la parte donde vive INSERT
                        if (strlen($header) > self::MAX_INSERT_HEADER_BYTES) {
                            // Si llevamos 16 KB sin ver "INSERT INTO ... VALUES" en una línea
                            // posiblemente estamos en CREATE TABLE / dump de schema. Conservar
                            // los últimos bytes para no perder un INSERT que esté empezando.
                            $header = substr($header, -2048);
                        }

                        if ($c === 'S' || $c === 's') {
                            // Posible fin de "VALUES" — intentar match del header completo.
                            // La lista de columnas `(col1, col2, ...)` es OPCIONAL porque
                            // mysqldump/mysqldump-php por default emiten
                            //   INSERT INTO `tabla` VALUES (...)
                            // SIN la lista. Sin esto el parser detectaba 0 tablas en dumps reales.
                            if (preg_match(
                                '/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*(?:\(([^)]+)\))?\s*VALUES\s*$/is',
                                $header,
                                $m
                            )) {
                                $currentTable   = $m[1];
                                $skipCurrentInsert = !$this->shouldScanSqlRowsForTable($currentTable);
                                $currentColumns = isset($m[2]) && $m[2] !== ''
                                    ? array_map(
                                        fn ($x) => trim($x, " `\t\n\r"),
                                        explode(',', $m[2])
                                    )
                                    : []; // sin lista → tuplas se guardan como arrays indexados
                                $header   = '';
                                $tuple    = '';
                                $depth    = 0;
                                $inString = false;
                                $escape   = false;
                            }
                        }
                        continue;
                    }

                    // ─── ESTADO: DENTRO DE VALUES — consumiendo tuplas (...) ───
                    if ($escape) { $tuple .= $c; $escape = false; continue; }
                    if ($c === '\\') { $tuple .= $c; $escape = true; continue; }
                    if ($c === "'") { $inString = !$inString; $tuple .= $c; continue; }

                    if (!$inString) {
                        if ($c === '(') {
                            if ($depth === 0) { $tuple = ''; }
                            else              { $tuple .= $c; }
                            $depth++;
                            continue;
                        }
                        if ($c === ')') {
                            $depth--;
                            if ($depth === 0) {
                                // Tupla cerrada — procesarla.
                                // Si hay columnas declaradas, combinamos por nombre y validamos
                                // que la cardinalidad coincida (descarta tuplas malformadas).
                                // Si NO hay columnas (caso mysqldump default), guardamos como
                                // array indexado — el report sigue siendo válido (table+records+
                                // sample). executeImport puede resolver las columnas leyendo
                                // SHOW COLUMNS FROM la tabla destino al momento de aplicar.
                                if (!$skipCurrentInsert) {
                                    $values = $this->parseSqlTuple($tuple);
                                    $accept = empty($currentColumns)
                                        ? !empty($values)
                                        : (count($values) === count($currentColumns));
                                    if ($accept) {
                                        $mappedRow = empty($currentColumns)
                                            ? $values
                                            : array_combine($currentColumns, $values);
                                        $rowCounts[$currentTable] = ($rowCounts[$currentTable] ?? 0) + 1;
                                        if ($rowStoreLimit !== null && $rowCounts[$currentTable] <= $rowStoreLimit) {
                                            $datasets[$currentTable][] = $mappedRow;
                                        }
                                        if ($onAcceptedRow) {
                                            $onAcceptedRow($currentTable, $mappedRow);
                                        }
                                    }
                                }
                                $tuple = '';
                                continue;
                            }
                            $tuple .= $c;
                            continue;
                        }
                        if ($c === ';' && $depth === 0) {
                            // Fin del INSERT — volver a estado "buscando header"
                            $currentTable   = null;
                            $currentColumns = [];
                            $skipCurrentInsert = false;
                            $tuple          = '';
                            $header         = '';
                            continue;
                        }
                    }

                    if ($depth > 0) {
                        $tuple .= $c;
                    }
                    // bytes entre tuplas (`,`, espacios, `\n`) se ignoran
                }
            }
        } finally {
            fclose($handle);
        }

        // Acumular counts exactos. Importante usar += porque parseZip() puede llamar
        // parseSql() para múltiples .sql del ZIP, y analyzeFile() ya resetea al inicio.
        foreach ($rowCounts as $table => $count) {
            $this->lastRowCounts[$table] = ($this->lastRowCounts[$table] ?? 0) + $count;
        }

        return $datasets;
    }

    private function executeSqlImportFromPath(
        string $path,
        array $plan,
        array &$summary,
        array &$rowIndexes,
        array &$startedTables,
        ?callable $onTableStarted
    ): void {
        $chunks = [];
        $spoolDir = storage_path(self::STORAGE_DIR . '/spool-' . Str::uuid());
        if (!File::exists($spoolDir)) {
            File::makeDirectory($spoolDir, 0775, true, true);
        }
        $spools = [];
        $handles = [];

        $flushTableChunk = function (string $table) use (&$chunks, &$summary, $plan): void {
            $rows = $chunks[$table] ?? [];
            if ($rows === []) {
                return;
            }

            $tablePlan = $plan['tables'][$table] ?? null;
            if (!$tablePlan || ($tablePlan['disabled'] ?? false)) {
                $summary[$table]['errors'] = ($summary[$table]['errors'] ?? 0) + count($rows);
                $chunks[$table] = [];
                return;
            }

            $mode = $tablePlan['mode'] ?? self::GLOBAL_MODE_SMART;
            $targetExistedBefore = (bool) ($tablePlan['target_exists_before'] ?? false);

            if ($mode === self::GLOBAL_MODE_SKIP_EXISTING && $targetExistedBefore) {
                $summary[$table]['skipped'] = ($summary[$table]['skipped'] ?? 0) + count($rows);
                $chunks[$table] = [];
                return;
            }

            if (
                $mode === self::GLOBAL_MODE_FORCE_SOURCE
                || ($mode === self::GLOBAL_MODE_SKIP_EXISTING && !$targetExistedBefore)
            ) {
                $chunkSummary = $this->executeDirectInsertTable($table, $rows);
            } elseif ($mode === self::GLOBAL_MODE_SMART) {
                $chunkSummary = [
                    $table => $this->executeRawMergeTable($table, $tablePlan['info'], $rows),
                ];
            } else {
                $chunkSummary = $this->executeImport(
                    [$table => $rows],
                    [$table => ['action' => 'replace', 'conflicts' => []]]
                );
            }

            $summary[$table] = $this->mergeImportSummary(
                $summary[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0],
                $chunkSummary[$table] ?? ['imported' => 0, 'skipped' => 0, 'errors' => 0]
            );

            $chunks[$table] = [];
        };

        try {
            $this->scanSqlFile($path, null, function (string $table, array $row) use (&$spools, &$handles, &$rowIndexes, $spoolDir): void {
                if (!isset($spools[$table])) {
                    $path = $spoolDir . '/' . $this->spoolFileNameForTable($table);
                    $handle = @fopen($path, 'ab');
                    if (!$handle) {
                        throw new \RuntimeException('No se pudo crear spool temporal para ' . $table . '.');
                    }
                    $spools[$table] = [
                        'path' => $path,
                        'rows' => 0,
                    ];
                    $handles[$table] = $handle;
                }

                $rowIndexes[$table] = ($rowIndexes[$table] ?? 0);
                $payload = base64_encode(serialize([$rowIndexes[$table], $row]));
                fwrite($handles[$table], $payload . PHP_EOL);
                $spools[$table]['rows']++;
                $rowIndexes[$table]++;
            }, null);

            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
            $handles = [];

            foreach ($plan['ordered_tables'] as $table) {
                $spool = $spools[$table] ?? null;
                if (!$spool || !File::exists($spool['path'])) {
                    continue;
                }

                if (!isset($startedTables[$table])) {
                    $startedTables[$table] = true;
                    if ($onTableStarted) {
                        $onTableStarted($table);
                    }
                }

                foreach ($this->readRowsFromSpool($spool['path']) as $index => $row) {
                    $chunks[$table][$index] = $row;
                    if (count($chunks[$table]) >= self::BULK_CHUNK_SIZE) {
                        $flushTableChunk($table);
                    }
                }
                $flushTableChunk($table);
            }
        } finally {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
            File::deleteDirectory($spoolDir);
        }
    }

    private function spoolFileNameForTable(string $table): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $table) . '-' . sha1($table) . '.spool';
    }

    /**
     * @return \Generator<int, array>
     */
    private function readRowsFromSpool(string $path): \Generator
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = base64_decode(trim($line), true);
                if ($decoded === false) {
                    continue;
                }

                $payload = @unserialize($decoded);
                if (!is_array($payload) || count($payload) !== 2) {
                    continue;
                }

                [$index, $row] = $payload;
                if (!is_int($index) || !is_array($row)) {
                    continue;
                }

                yield $index => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stub conservado por si algún test/legacy lo invoca. La detección de fin
     * de statement ahora vive directamente en el FSM de parseSql().
     */
    private function findStatementEnd(string $buffer, int $startFrom = 0): int|false
    {
        $len = strlen($buffer);
        $inString = false;
        $escape = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $buffer[$i];
            if ($escape) { $escape = false; continue; }
            if ($c === '\\') { $escape = true; continue; }
            if ($c === "'") { $inString = !$inString; continue; }
            if (!$inString && $c === ';' && $i >= $startFrom) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Legacy: procesa un statement completo. Reemplazado por el FSM de parseSql();
     * conservado por compat — no se usa en el flujo actual.
     */
    private function processSqlStatement(string $statement, array &$datasets, array &$rowCounts): void
    {
        $stripped = ltrim($statement);
        $stripped = preg_replace('/^(--[^\n]*\n|\/\*.*?\*\/|\s+)+/s', '', $stripped) ?? $stripped;
        if (stripos($stripped, 'INSERT') !== 0) {
            return;
        }
        if (!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*(.+);\s*$/is', $stripped, $m)) {
            return;
        }
        $table       = $m[1];
        $columns     = array_map(fn ($c) => trim($c, " `\t\n\r"), explode(',', $m[2]));
        $valuesBlob  = rtrim($m[3]);
        $rows = $this->splitSqlTuples($valuesBlob);
        foreach ($rows as $tuple) {
            $values = $this->parseSqlTuple($tuple);
            if (count($values) !== count($columns)) {
                continue;
            }
            $rowCounts[$table] = ($rowCounts[$table] ?? 0) + 1;
            if (($rowCounts[$table] ?? 0) <= self::MAX_ROWS_PER_TABLE) {
                $datasets[$table][] = array_combine($columns, $values);
            }
        }
    }

    private function splitSqlTuples(string $blob): array
    {
        $tuples = [];
        $depth = 0;
        $current = '';
        $inString = false;
        $escape = false;
        $len = strlen($blob);
        for ($i = 0; $i < $len; $i++) {
            $c = $blob[$i];
            if ($escape) { $current .= $c; $escape = false; continue; }
            if ($c === '\\') { $current .= $c; $escape = true; continue; }
            if ($c === "'" ) { $inString = !$inString; $current .= $c; continue; }
            if (!$inString) {
                if ($c === '(') { if ($depth === 0) { $current = ''; } else { $current .= $c; } $depth++; continue; }
                if ($c === ')') { $depth--; if ($depth === 0) { $tuples[] = $current; $current = ''; continue; } else { $current .= $c; continue; } }
            }
            if ($depth > 0) $current .= $c;
        }
        return $tuples;
    }

    private function parseSqlTuple(string $tuple): array
    {
        $values = [];
        $len = strlen($tuple);
        $i = 0;
        $current = '';
        $inString = false;
        $currentQuoted = false;
        while ($i < $len) {
            $c = $tuple[$i];
            if ($inString) {
                if ($c === '\\' && $i + 1 < $len) { $current .= $tuple[$i + 1]; $i += 2; continue; }
                if ($c === "'") { $inString = false; $i++; continue; }
                $current .= $c; $i++; continue;
            }
            if ($c === "'") { $inString = true; $currentQuoted = true; $i++; continue; }
            if ($c === ',') {
                $values[] = $this->castSqlValue(trim($current), $currentQuoted);
                $current = '';
                $currentQuoted = false;
                $i++;
                continue;
            }
            $current .= $c; $i++;
        }
        if ($current !== '' || $tuple !== '' || $currentQuoted) {
            $values[] = $this->castSqlValue(trim($current), $currentQuoted);
        }
        return $values;
    }

    private function castSqlValue(string $raw, bool $quoted = false)
    {
        if ($quoted) return $raw;
        if ($raw === '') return null;
        if (strcasecmp($raw, 'NULL') === 0) return null;
        if (is_numeric($raw)) return $raw + 0;
        return trim($raw, "'");
    }

    private function parseJson(string $path): array
    {
        $data = json_decode(file_get_contents($path) ?: 'null', true);
        if (!is_array($data)) return [];
        $datasets = [];
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            foreach ($data as $table => $rows) {
                if (is_array($rows)) {
                    $datasets[$table] = array_values(array_filter($rows, 'is_array'));
                }
            }
            return $datasets;
        }
        $datasets['unknown'] = $data;
        return $datasets;
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return [];
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return []; }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) continue;
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);
        $table = $this->guessTableFromHeader($header) ?? 'unknown';
        return [$table => $rows];
    }

    private function parseSpreadsheet(string $path): array
    {
        $reader = SpreadsheetIOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $datasets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (count($rows) < 2) continue;
            $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
            $tableName = strtolower($sheet->getTitle());
            $legacyRule = self::TABLE_MODULE_MAP[$tableName] ?? null;
            $tableKey = $this->tableResolver()->hasDescriptor($tableName, $legacyRule)
                ? $tableName
                : ($this->guessTableFromHeader($header) ?? $tableName);
            $records = [];
            for ($i = 1, $n = count($rows); $i < $n; $i++) {
                if (count($rows[$i]) !== count($header)) continue;
                $records[] = array_combine($header, $rows[$i]);
            }
            if (!empty($records)) {
                $datasets[$tableKey] = $records;
            }
        }
        return $datasets;
    }

    private function guessTableFromHeader(array $header): ?string
    {
        $needle = array_map('strtolower', $header);
        foreach ($this->tableResolver()->conflictKeyHints(self::TABLE_MODULE_MAP) as $table => $keys) {
            $hits = 0;
            foreach ($keys as $key) {
                if (in_array($key, $needle, true)) $hits++;
            }
            if ($hits >= max(1, (int) floor(count($keys) / 2))) {
                return $table;
            }
        }
        return null;
    }

    private function findExisting(string $table, array $keys, array $row): ?array
    {
        if (empty($keys)) return null;
        $query = DB::table($table);
        $hasFilter = false;
        foreach ($keys as $key) {
            if (!empty($row[$key]) && $this->hasColumnCached($table, $key)) {
                $query->orWhere($key, $row[$key]);
                $hasFilter = true;
            }
        }
        if (!$hasFilter) return null;
        $found = $query->first();
        return $found ? (array) $found : null;
    }

    /**
     * Encuentra filas existentes para TODAS las filas entrantes en UNA sola query.
     * Reduce O(R) queries por tabla → O(1).
     *
     * @return array<int, array>  rowIndex => existingRow
     */
    private function batchFindExisting(string $table, array $keys, array $rows): array
    {
        if (empty($keys) || empty($rows)) {
            return [];
        }

        // 1. Colectar valores únicos por cada conflict_key
        $valuesByKey = [];
        foreach ($keys as $key) {
            if (!$this->hasColumnCached($table, $key)) continue;
            $valuesByKey[$key] = [];
        }
        if (empty($valuesByKey)) return [];

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                if (!isset($valuesByKey[$key])) continue;
                if ($this->hasConflictValue($row, $key)) {
                    $valuesByKey[$key][] = $row[$key];
                }
            }
        }

        // Deduplicar
        foreach ($valuesByKey as $key => $values) {
            $valuesByKey[$key] = array_unique($values);
        }

        // 2. Query única: WHERE key1 IN (v1,v2,...) OR key2 IN (v1,v2,...)
        $query = DB::table($table);
        $hasFilter = false;
        foreach ($valuesByKey as $key => $values) {
            if (!empty($values)) {
                $query->orWhereIn($key, $values);
                $hasFilter = true;
            }
        }
        if (!$hasFilter) return [];

        $existingRows = $query->get();
        if ($existingRows->isEmpty()) return [];

        // 3. Indexar existentes por cada key: keyValue → existingRow[]
        $index = [];
        foreach ($existingRows as $existing) {
            $existing = (array) $existing;
            foreach ($keys as $key) {
                if (!isset($valuesByKey[$key])) continue;
                if ($this->hasConflictValue($existing, $key)) {
                    $index[$key][(string) $existing[$key]][] = $existing;
                }
            }
        }

        // 4. Para cada fila entrante, buscar matching en memoria
        $result = [];
        foreach ($rows as $idx => $row) {
            foreach ($keys as $key) {
                if (!isset($valuesByKey[$key])) continue;
                if (!$this->hasConflictValue($row, $key)) continue;
                $candidates = $index[$key][(string) $row[$key]] ?? [];
                foreach ($candidates as $candidate) {
                    // Verificar que TODOS los valores de conflict_keys coincidan
                    $allMatch = true;
                    foreach ($keys as $k) {
                        if (!$this->hasConflictValue($row, $k) || !$this->hasConflictValue($candidate, $k)) {
                            $allMatch = false;
                            break;
                        }
                        if ((string) $row[$k] !== (string) $candidate[$k]) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        $result[$idx] = $candidate;
                        break 2; // salir de keys + candidates
                    }
                }
            }
        }

        return $result;
    }

    private function hasConflictValue(array $row, string $key): bool
    {
        return array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '';
    }

    private function resolveConflictKeys(string $table, ?array $mapped): array
    {
        return $this->resolveConflictIdentity($table, $mapped)['keys'];
    }

    private function resolveConflictIdentity(string $table, ?array $mapped): array
    {
        $preferExplicit = ($mapped['identity_priority'] ?? null) === 'override';
        if ($preferExplicit) {
            $explicitIdentity = $this->resolveExplicitConflictIdentity($table, $mapped);
            if ($explicitIdentity !== null) {
                return $explicitIdentity;
            }
        }

        if (Schema::hasTable($table)) {
            $fromIndexes = $this->inferConflictKeysFromTarget($table);
            $usableIndexes = $this->filterUsableConflictKeys($table, $fromIndexes);
            if ($usableIndexes !== []) {
                return [
                    'keys' => $usableIndexes,
                    'source' => in_array('id', $usableIndexes, true) ? 'target_primary_key' : 'target_unique_index',
                ];
            }
        }

        if (!$preferExplicit) {
            $explicitIdentity = $this->resolveExplicitConflictIdentity($table, $mapped);
            if ($explicitIdentity !== null) {
                return $explicitIdentity;
            }
        }

        $sourceColumns = $this->sourceTableSchemas[$table]['columns']
            ?? $this->dumpColumnsByTable[$table]
            ?? [];

        if (in_array('id', $sourceColumns, true)) {
            return [
                'keys' => ['id'],
                'source' => 'source_id',
            ];
        }

        return [
            'keys' => [],
            'source' => 'none',
        ];
    }

    private function resolveExplicitConflictIdentity(string $table, ?array $mapped): ?array
    {
        $explicit = is_array($mapped) ? array_values(array_filter($mapped['conflict_keys'] ?? [])) : [];
        if ($explicit === []) {
            return null;
        }

        $usableExplicit = $this->filterUsableConflictKeys($table, $explicit);
        if ($usableExplicit === []) {
            return null;
        }

        return [
            'keys' => $usableExplicit,
            'source' => $mapped['conflict_keys_source'] ?? 'explicit_conflict_keys',
        ];
    }

    private function filterUsableConflictKeys(string $table, array $keys): array
    {
        $keys = array_values(array_filter($keys));
        if ($keys === [] || !$this->targetTableExistsCached($table)) {
            return [];
        }

        $metadata = $this->tableMetadata($table);
        $sourceColumns = $this->sourceTableSchemas[$table]['columns']
            ?? $this->dumpColumnsByTable[$table]
            ?? [];
        $sourceColumnsFlip = $sourceColumns === [] ? [] : array_flip($sourceColumns);

        foreach ($keys as $key) {
            if (!isset($metadata['columns_flip'][$key])) {
                return [];
            }
            if ($sourceColumnsFlip !== [] && !isset($sourceColumnsFlip[$key])) {
                return [];
            }
        }

        return $keys;
    }

    private function prepareRowsForConflictDetection(string $table, array $rows): array
    {
        $prepared = [];
        foreach ($rows as $index => $row) {
            $prepared[$index] = $this->sanitizeRow($row, $table);
        }

        return $prepared;
    }

    private function inferConflictKeysFromTarget(string $table): array
    {
        try {
            $indexes = DB::select('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`');
        } catch (Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($indexes as $index) {
            $nonUnique = (int) ($index->Non_unique ?? 1);
            if ($nonUnique !== 0) {
                continue;
            }

            $keyName = (string) ($index->Key_name ?? '');
            $seq = (int) ($index->Seq_in_index ?? 1);
            $column = (string) ($index->Column_name ?? '');
            if ($keyName === '' || $column === '') {
                continue;
            }
            $grouped[$keyName][$seq] = $column;
        }

        if (isset($grouped['PRIMARY'])) {
            ksort($grouped['PRIMARY']);
            return array_values($grouped['PRIMARY']);
        }

        foreach ($grouped as $columns) {
            ksort($columns);
            if ($columns !== []) {
                return array_values($columns);
            }
        }

        return [];
    }

    private function hasColumnCached(string $table, string $column): bool
    {
        if (!isset($this->hasColumnCache[$table][$column])) {
            $metadata = $this->tableMetadata($table);
            $this->hasColumnCache[$table][$column] = isset($metadata['columns_flip'][$column]);
        }
        return $this->hasColumnCache[$table][$column];
    }

    private function shouldScanSqlRowsForTable(string $table): bool
    {
        return isset($this->sourceTableSchemas[$table]) || $this->targetTableExistsCached($table);
    }

    private function targetTableExistsCached(string $table): bool
    {
        if (!array_key_exists($table, $this->targetTableExistsCache)) {
            $this->targetTableExistsCache[$table] = Schema::hasTable($table);
        }

        return $this->targetTableExistsCache[$table];
    }

    private function matchedKeys(array $keys, array $row, array $existing): array
    {
        $matched = [];
        foreach ($keys as $key) {
            if (isset($row[$key], $existing[$key]) && (string) $row[$key] === (string) $existing[$key]) {
                $matched[] = $key;
            }
        }
        return $matched;
    }

    private function sanitizeRow(array $row, string $table): array
    {
        $metadata = $this->tableMetadata($table);
        $destColumns = $metadata['columns'];
        if ($row !== [] && array_is_list($row)) {
            // Rows con keys numéricas vienen del parser cuando el INSERT del
            // dump NO trae lista de columnas (mysqldump default). Necesitamos
            // mapear posición → nombre. Hay 3 estrategias en orden de preferencia:
            //
            // 1) Columnas del DUMP (extraídas del CREATE TABLE): siempre
            //    coinciden con la cardinalidad de la fila — es el origen
            //    autoritativo. Maneja schema drift sin perder datos.
            // 2) Columnas del destino (current Schema): solo si la cardinalidad
            //    coincide exactamente. Funciona cuando no hay drift.
            // 3) Devolver [] cuando no podemos mapear con confianza — la fila
            //    se contabiliza como error en lugar de insertarse desalineada.
            $dumpCols = $this->dumpColumnsByTable[$table] ?? null;
            if ($dumpCols && count($dumpCols) === count($row)) {
                $row = array_combine($dumpCols, $row);
            } elseif (count($destColumns) === count($row)) {
                $row = array_combine($destColumns, $row);
            } else {
                return [];
            }
        }
        // Filtramos a las columnas que existen en el destino — drop columns
        // que ya no existen en BD (eliminadas desde el dump), y BD aporta
        // sus columnas nuevas como NULL/default vía mass-assignment fallback.
        return array_intersect_key($row, $metadata['columns_flip']);
    }

    /**
     * Pre-pass del archivo SQL para extraer el esquema fuente por tabla.
     *
     * Se usa para:
     * - mapear filas indexadas -> columnas reales del dump
     * - crear tablas faltantes desde el dump
     * - detectar drift de columnas y dependencias entre tablas
     *
     * @return array<string, array{
     *     table: string,
     *     columns: string[],
     *     column_definitions: array<string, string>,
     *     key_definitions: string[],
     *     foreign_key_definitions: string[],
     *     dependencies: string[],
     *     create_sql: string,
     *     table_options: string
     * }>
     */
    private function extractDumpTableSchemas(string $path): array
    {
        $result = [];
        $handle = @fopen($path, 'rb');
        if (!$handle) return [];
        $buf = '';
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 64 * 1024);
                if ($chunk === false || $chunk === '') break;
                $buf .= $chunk;
                // Procesar todos los CREATE TABLE completos que estén en el buffer.
                while (preg_match(
                    '/CREATE\s+TABLE\s+`?(\w+)`?\s*\((.*?)\)\s*((?:ENGINE|TYPE)\s*=.*?);/is',
                    $buf,
                    $m,
                    PREG_OFFSET_CAPTURE
                )) {
                    $name = $m[1][0];
                    $body = $m[2][0];
                    $tableOptions = trim($m[3][0]);
                    $columnDefinitions = [];
                    $keyDefinitions = [];
                    $foreignKeyDefinitions = [];
                    $dependencies = [];

                    foreach (preg_split('/\R/', $body) ?: [] as $line) {
                        $trimmed = trim(trim($line), ',');
                        if ($trimmed === '') {
                            continue;
                        }

                        if (preg_match('/^`(\w+)`\s+(.+)$/', $trimmed, $columnMatch)) {
                            $columnDefinitions[$columnMatch[1]] = $trimmed;
                            continue;
                        }

                        if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|FULLTEXT KEY|SPATIAL KEY)\b/i', $trimmed)) {
                            $keyDefinitions[] = $trimmed;
                            continue;
                        }

                        if (preg_match('/^(?:CONSTRAINT\s+`?\w+`?\s+)?FOREIGN KEY\b/i', $trimmed)) {
                            $foreignKeyDefinitions[] = $trimmed;
                            if (preg_match('/REFERENCES\s+`?(\w+)`?/i', $trimmed, $dependencyMatch)) {
                                $dependencies[] = $dependencyMatch[1];
                            }
                            continue;
                        }
                    }

                    $result[$name] = [
                        'table'                   => $name,
                        'columns'                 => array_keys($columnDefinitions),
                        'column_definitions'      => $columnDefinitions,
                        'key_definitions'         => $keyDefinitions,
                        'foreign_key_definitions' => $foreignKeyDefinitions,
                        'dependencies'            => array_values(array_unique(array_filter($dependencies))),
                        'create_sql'              => trim($m[0][0]),
                        'table_options'           => $tableOptions,
                    ];

                    $endPos = $m[0][1] + strlen($m[0][0]);
                    $buf = substr($buf, $endPos);
                }

                if (strlen($buf) > 256 * 1024) {
                    $buf = substr($buf, -256 * 1024);
                }
            }
        } finally {
            fclose($handle);
        }
        return $result;
    }

    private function resetAnalysisState(): void
    {
        $this->lastRowCounts = [];
        $this->dumpColumnsByTable = [];
        $this->sourceTableSchemas = [];
        $this->hasColumnCache = [];
        $this->tableMetadataCache = [];
        $this->targetTableExistsCache = [];
        $this->targetForeignKeyDependencyCache = [];
        $this->targetForeignKeyMetadataCache = [];
        $this->referencedValueExistsCache = [];
        $this->foreignKeyFallbackValueCache = [];
    }

    private function buildAnalysisReport(array $datasets): array
    {
        $tables = array_values(array_unique(array_merge(
            array_keys($this->sourceTableSchemas),
            array_keys($datasets)
        )));

        $report = [];
        foreach ($tables as $table) {
            $rows = array_values($datasets[$table] ?? []);
            $schema = $this->sourceTableSchemas[$table] ?? null;
            $info = $this->importDescriptor($table);
            $targetExists = Schema::hasTable($table);
            $targetColumns = $targetExists ? Schema::getColumnListing($table) : [];
            $sourceColumns = $schema['columns'] ?? ($this->dumpColumnsByTable[$table] ?? []);
            $missingTargetColumns = array_values(array_diff($sourceColumns, $targetColumns));
            $extraTargetColumns = array_values(array_diff($targetColumns, $sourceColumns));
            $warnings = $info['warnings'] ?? [];

            if (!$targetExists) {
                $warnings[] = 'tabla_destino_inexistente';
            }
            if ($schema === null) {
                $warnings[] = 'sin_create_table';
            }
            if ($missingTargetColumns !== []) {
                $warnings[] = 'columnas_faltantes_en_destino';
            }
            if ($extraTargetColumns !== []) {
                $warnings[] = 'columnas_extra_en_destino';
            }

            $exactCount = $this->lastRowCounts[$table] ?? count($rows);
            $report[] = [
                'table'                  => $table,
                'module'                 => $info['module'],
                'model'                  => $info['model'],
                'mode'                   => $info['mode'],
                'records'                => $exactCount,
                'sample'                 => array_slice($rows, 0, 3),
                'known'                  => $info['known'],
                'has_model'              => $info['has_model'],
                'descriptor_source'      => $info['descriptor_source'],
                'identity_source'        => $info['identity_source'],
                'model_candidates'       => $info['model_candidates'] ?? [],
                'target_exists'          => $targetExists,
                'truncated'              => $exactCount > count($rows),
                'source_columns'         => $sourceColumns,
                'target_columns'         => $targetColumns,
                'missing_target_columns' => $missingTargetColumns,
                'extra_target_columns'   => $extraTargetColumns,
                'dependencies'           => $schema['dependencies'] ?? [],
                'warnings'               => array_values(array_unique($warnings)),
            ];
        }

        return $this->sortReportByDependencies($report);
    }

    private function importDescriptor(string $table): array
    {
        $descriptor = $this->tableResolver()->resolve($table, self::TABLE_MODULE_MAP[$table] ?? null);
        $identity = $this->resolveConflictIdentity($table, $descriptor['conflict_rule'] ?? null);
        $descriptor['conflict_keys'] = $identity['keys'];
        $descriptor['identity_source'] = $identity['source'];
        unset($descriptor['conflict_rule']);

        return $descriptor;
    }

    private function sortReportByDependencies(array $report): array
    {
        $tables = array_map(static fn (array $row) => $row['table'], $report);
        $ordered = $this->topologicalSortTables($tables);
        $indexed = [];
        foreach ($report as $row) {
            $indexed[$row['table']] = $row;
        }

        $sorted = [];
        foreach ($ordered as $table) {
            if (isset($indexed[$table])) {
                $sorted[] = $indexed[$table];
                unset($indexed[$table]);
            }
        }

        foreach ($indexed as $row) {
            $sorted[] = $row;
        }

        return $sorted;
    }

    private function tableResolver(): SmartImportTableResolver
    {
        if ($this->tableResolver === null) {
            $this->tableResolver = new SmartImportTableResolver();
        }

        return $this->tableResolver;
    }

    private function topologicalSortTables(array $tables): array
    {
        $tables = array_values(array_unique($tables));
        $inDegree = array_fill_keys($tables, 0);
        $adjacency = array_fill_keys($tables, []);

        foreach ($tables as $table) {
            foreach ($this->dependenciesForTable($table) as $dependency) {
                if (!isset($inDegree[$dependency])) {
                    continue;
                }
                $adjacency[$dependency][] = $table;
                $inDegree[$table]++;
            }
        }

        $queue = [];
        foreach ($tables as $table) {
            if (($inDegree[$table] ?? 0) === 0) {
                $queue[] = $table;
            }
        }

        $ordered = [];
        while ($queue !== []) {
            $table = array_shift($queue);
            $ordered[] = $table;

            foreach ($adjacency[$table] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($ordered) !== count($tables)) {
            foreach ($tables as $table) {
                if (!in_array($table, $ordered, true)) {
                    $ordered[] = $table;
                }
            }
        }

        return $ordered;
    }

    private function dependenciesForTable(string $table): array
    {
        $dependencies = $this->sourceTableSchemas[$table]['dependencies'] ?? [];
        foreach ($this->targetForeignKeyDependencies($table) as $dependency) {
            $dependencies[] = $dependency;
        }

        return array_values(array_unique(array_filter(
            $dependencies,
            static fn (string $dependency) => $dependency !== $table
        )));
    }

    private function targetForeignKeyDependencies(string $table): array
    {
        if (!isset($this->targetForeignKeyDependencyCache[$table])) {
            $this->targetForeignKeyDependencyCache[$table] = array_values(array_unique(array_filter(array_map(
                static fn (array $foreignKey) => $foreignKey['referenced_table'] ?? '',
                $this->targetForeignKeyMetadata($table)
            ))));
        }

        return $this->targetForeignKeyDependencyCache[$table];
    }

    /**
     * @return array<string, array{column: string, referenced_table: string, referenced_column: string, nullable: bool}>
     */
    private function targetForeignKeyMetadata(string $table): array
    {
        if (!$this->targetTableExistsCached($table)) {
            return [];
        }

        if (!isset($this->targetForeignKeyMetadataCache[$table])) {
            try {
                $database = DB::connection()->getDatabaseName();
                $rows = DB::select(
                    'SELECT
                        kcu.COLUMN_NAME AS column_name,
                        kcu.REFERENCED_TABLE_NAME AS referenced_table,
                        kcu.REFERENCED_COLUMN_NAME AS referenced_column,
                        c.IS_NULLABLE AS is_nullable
                     FROM information_schema.KEY_COLUMN_USAGE kcu
                     INNER JOIN information_schema.COLUMNS c
                        ON c.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                       AND c.TABLE_NAME = kcu.TABLE_NAME
                       AND c.COLUMN_NAME = kcu.COLUMN_NAME
                     WHERE kcu.TABLE_SCHEMA = ?
                       AND kcu.TABLE_NAME = ?
                       AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                     ORDER BY kcu.ORDINAL_POSITION',
                    [$database, $table]
                );

                $metadata = [];
                foreach ($rows as $row) {
                    $column = (string) ($row->column_name ?? '');
                    $referencedTable = (string) ($row->referenced_table ?? '');
                    $referencedColumn = (string) ($row->referenced_column ?? '');
                    if ($column === '' || $referencedTable === '' || $referencedColumn === '') {
                        continue;
                    }

                    $metadata[$column] = [
                        'column'            => $column,
                        'referenced_table'  => $referencedTable,
                        'referenced_column' => $referencedColumn,
                        'nullable'          => strtoupper((string) ($row->is_nullable ?? 'NO')) === 'YES',
                    ];
                }

                $this->targetForeignKeyMetadataCache[$table] = $metadata;
            } catch (Throwable) {
                $this->targetForeignKeyMetadataCache[$table] = [];
            }
        }

        return $this->targetForeignKeyMetadataCache[$table];
    }

    /**
     * Valida que la entrada del map sea ejecutable. Devuelve null si OK,
     * o un string con la razón del skip ('tabla_no_mapeada', etc.).
     */
    private function validateMapEntry(string $table, ?array $info): ?string
    {
        if (!Schema::hasTable($table)) {
            return 'tabla_destino_inexistente';
        }
        return null;
    }

    /** PK de la tabla. Modelo Eloquent → getKeyName(); raw → 'id'. */
    private function pkOf(string $table, array $info): string
    {
        if (($info['mode'] ?? 'model') === 'model') {
            return (new $info['model']())->getKeyName();
        }
        return 'id';
    }

    /** Insert ramificado por modo. Raw inyecta timestamps + user-stamp si la tabla los tiene. */
    private function insertRow(string $table, array $info, array $row): void
    {
        if (($info['mode'] ?? 'model') === 'model') {
            $modelClass = $info['model'];
            $modelClass::create($this->prepareModelWriteRow($table, $row));
            return;
        }
        $clean = $this->prepareDirectWriteRow($table, $row, isUpdate: false);
        DB::table($table)->insert($clean);
    }

    /** Update ramificado por modo. */
    private function updateRow(string $table, array $info, mixed $existingId, array $row): void
    {
        if (($info['mode'] ?? 'model') === 'model') {
            $modelClass = $info['model'];
            $modelClass::query()->whereKey($existingId)
                ->update($this->prepareModelWriteRow($table, $row));
            return;
        }
        $clean = $this->prepareDirectWriteRow($table, $row, isUpdate: true);
        DB::table($table)->where($this->pkOf($table, $info), $existingId)->update($clean);
    }

    private function prepareModelWriteRow(string $table, array $row): array
    {
        $clean = $this->sanitizeRow($row, $table);
        if ($clean === []) {
            return [];
        }

        return $this->normalizeForeignKeysForRow($table, $clean);
    }

    /**
     * Replica el auto-stamp de BaseModel (created_by/updated_by) y los
     * timestamps de Eloquent para tablas que no pasan por un modelo Eloquent.
     */
    private function injectAuditColumns(string $table, array $row, bool $isUpdate): array
    {
        $metadata = $this->tableMetadata($table);
        $userId = $this->actingUserId ?? auth()->id();
        $now = now();
        if ($metadata['has_updated_at']) {
            $row['updated_at'] = $now;
        }
        if ($metadata['has_updated_by'] && $userId) {
            $row['updated_by'] = $userId;
        }
        if (!$isUpdate) {
            if ($metadata['has_created_at'] && !isset($row['created_at'])) {
                $row['created_at'] = $now;
            }
            if ($metadata['has_created_by'] && $userId && !isset($row['created_by'])) {
                $row['created_by'] = $userId;
            }
        }
        return $row;
    }

    private function canUseBulkPath(array $info): bool
    {
        if (($info['mode'] ?? 'model') === 'raw') {
            return true;
        }

        if (($info['strict_model'] ?? false) === true) {
            return false;
        }

        $modelClass = $info['model'] ?? null;
        if (!$modelClass || !class_exists($modelClass)) {
            return false;
        }

        return !in_array($modelClass, self::STRICT_MODEL_CLASSES, true);
    }

    private function executeBulkImportTable(
        string $table,
        array $info,
        array $rows,
        array $existingMap,
        string $defaultAction,
        array $perRow
    ): array {
        $pk = $this->pkOf($table, $info);
        $hasSinglePk = $this->hasColumnCached($table, $pk);
        $insertRows = [];
        $updateRows = [];
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $index => $row) {
            try {
                $existing = $existingMap[$index] ?? null;
                $action = $existing ? ($perRow[$index] ?? $defaultAction) : 'insert';

                if ($action === 'skip') {
                    $skipped++;
                    continue;
                }

                if ($action === 'replace' && $existing) {
                    $prepared = $this->prepareDirectWriteRow($table, $row, isUpdate: true, normalizeForeignKeys: false);
                    if ($prepared === []) {
                        $errors++;
                        continue;
                    }
                    if ($hasSinglePk && isset($existing[$pk])) {
                        $prepared[$pk] = $existing[$pk];
                        $updateRows[] = $prepared;
                    } else {
                        $this->updateExistingRowByConflictKeys($table, $info['conflict_keys'], $row);
                        $imported++;
                    }
                    continue;
                }

                if ($action === 'duplicate') {
                    unset($row[$pk]);
                }

                $prepared = $this->prepareDirectWriteRow($table, $row, isUpdate: false, normalizeForeignKeys: false);
                if ($prepared === []) {
                    $errors++;
                    continue;
                }
                $insertRows[] = $prepared;
            } catch (Throwable $e) {
                Log::warning('SmartImport bulk prepare error [' . $table . ']: ' . $e->getMessage());
                $errors++;
            }
        }

        $imported += $this->flushBulkInsertRows($table, $insertRows, $errors);
        $imported += $this->flushBulkUpdateRows($table, $pk, $updateRows, $errors);

        return compact('imported', 'skipped', 'errors');
    }

    private function executeRawMergeTable(string $table, array $info, array $rows): array
    {
        $keys = $this->rawMergeKeys($table, $info);
        $mergeRows = [];
        $insertRows = [];
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $prepared = $this->prepareRawImportRow($table, $row);
                if ($prepared === []) {
                    $errors++;
                    continue;
                }

                if ($keys !== [] && $this->rowHasAllKeys($prepared, $keys)) {
                    $mergeRows[] = $prepared;
                } else {
                    $insertRows[] = $prepared;
                }
            } catch (Throwable $e) {
                Log::warning('SmartImport raw merge prepare error [' . $table . ']: ' . $e->getMessage());
                $errors++;
            }
        }

        $imported += $this->flushBulkMergeRows($table, $mergeRows, $keys, $errors);
        $imported += $this->flushBulkInsertRows($table, $insertRows, $errors, normalizeForeignKeys: false);

        return compact('imported', 'skipped', 'errors');
    }

    private function rawMergeKeys(string $table, array $info): array
    {
        $keys = array_values(array_filter($info['conflict_keys'] ?? []));
        if ($keys !== []) {
            return $keys;
        }

        if ($this->targetTableExistsCached($table)) {
            return $this->filterUsableConflictKeys($table, $this->inferConflictKeysFromTarget($table));
        }

        $sourceColumns = $this->sourceTableSchemas[$table]['columns']
            ?? $this->dumpColumnsByTable[$table]
            ?? [];

        return in_array('id', $sourceColumns, true) ? ['id'] : [];
    }

    private function rowHasAllKeys(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->hasConflictValue($row, $key)) {
                return false;
            }
        }

        return true;
    }

    private function prepareRawImportRow(string $table, array $row): array
    {
        return $this->sanitizeRow($row, $table);
    }

    private function prepareDirectWriteRow(string $table, array $row, bool $isUpdate, bool $normalizeForeignKeys = true): array
    {
        $clean = $this->sanitizeRow($row, $table);
        if ($clean === []) {
            return [];
        }

        $clean = $this->injectAuditColumns($table, $clean, $isUpdate);

        return $normalizeForeignKeys
            ? $this->normalizeForeignKeysForRow($table, $clean)
            : $clean;
    }

    private function normalizeForeignKeysForRow(string $table, array $row): array
    {
        $rows = $this->normalizeForeignKeysForRows($table, [$row]);

        return $rows[0] ?? $row;
    }

    private function normalizeForeignKeysForRows(string $table, array $rows): array
    {
        if (!$this->foreignKeyNormalizationEnabled() || $rows === []) {
            return $rows;
        }

        $foreignKeys = $this->targetForeignKeyMetadata($table);
        if ($foreignKeys === []) {
            return $rows;
        }

        foreach ($foreignKeys as $column => $foreignKey) {
            $checkReferencedExists = $foreignKey['referenced_table'] !== $table;
            $values = [];
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $value = $row[$column];
                if ($this->isBlankForeignKeyValue($value) || !is_scalar($value)) {
                    continue;
                }

                $values[$this->referencedValueCacheKey(
                    $foreignKey['referenced_table'],
                    $foreignKey['referenced_column'],
                    $value
                )] = $value;
            }

            if ($values === []) {
                continue;
            }

            $existence = $checkReferencedExists
                ? $this->referencedValuesExistenceMap(
                    $foreignKey['referenced_table'],
                    $foreignKey['referenced_column'],
                    array_values($values)
                )
                : [];

            foreach ($rows as $index => $row) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $value = $row[$column];
                if ($this->isBlankForeignKeyValue($value) || !is_scalar($value)) {
                    continue;
                }

                $cacheKey = $this->referencedValueCacheKey(
                    $foreignKey['referenced_table'],
                    $foreignKey['referenced_column'],
                    $value
                );
                $isInvalid = $this->isInvalidForeignKeySentinel($value)
                    || ($checkReferencedExists && !($existence[$cacheKey] ?? true));
                if (!$isInvalid) {
                    continue;
                }

                $replacement = $this->replacementForInvalidForeignKey($foreignKey);
                if ($replacement['replace']) {
                    $rows[$index][$column] = $replacement['value'];
                }
            }
        }

        return $rows;
    }

    private function foreignKeyNormalizationEnabled(): bool
    {
        return (bool) config('smart_import.foreign_key_normalization.enabled', true);
    }

    private function isBlankForeignKeyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function isInvalidForeignKeySentinel(mixed $value): bool
    {
        foreach ($this->invalidForeignKeySentinelValues() as $sentinel) {
            if ($value === $sentinel) {
                return true;
            }

            if (is_scalar($value) && is_scalar($sentinel) && (string) $value === (string) $sentinel) {
                return true;
            }
        }

        return false;
    }

    private function invalidForeignKeySentinelValues(): array
    {
        $values = config('smart_import.foreign_key_normalization.invalid_sentinel_values', [0, '0']);

        return is_array($values) ? $values : [0, '0'];
    }

    /**
     * @param array{column: string, referenced_table: string, referenced_column: string, nullable: bool} $foreignKey
     * @return array{replace: bool, value: mixed}
     */
    private function replacementForInvalidForeignKey(array $foreignKey): array
    {
        if ($foreignKey['nullable']) {
            return ['replace' => true, 'value' => null];
        }

        $fallback = $this->foreignKeyFallbackValue(
            $foreignKey['referenced_table'],
            $foreignKey['referenced_column']
        );

        if ($fallback['available']) {
            return ['replace' => true, 'value' => $fallback['value']];
        }

        return ['replace' => false, 'value' => null];
    }

    /**
     * @return array{available: bool, value: mixed}
     */
    private function foreignKeyFallbackValue(string $referencedTable, string $referencedColumn): array
    {
        $reference = $referencedTable . '.' . $referencedColumn;
        $fallbacks = config('smart_import.foreign_key_normalization.fallbacks', [
            'users.id' => 'acting_user_or_first_existing',
        ]);
        $rule = is_array($fallbacks) ? ($fallbacks[$reference] ?? null) : null;
        if ($rule === null || $rule === false) {
            return ['available' => false, 'value' => null];
        }

        if (array_key_exists($reference, $this->foreignKeyFallbackValueCache)) {
            return $this->foreignKeyFallbackValueCache[$reference];
        }

        $fallback = ['available' => false, 'value' => null];
        try {
            if ($rule === 'acting_user_or_first_existing') {
                foreach ([$this->actingUserId, auth()->id()] as $candidate) {
                    if ($candidate !== null && $this->referencedValueExistsCached($referencedTable, $referencedColumn, $candidate)) {
                        $fallback = ['available' => true, 'value' => $candidate];
                        break;
                    }
                }

                if (!$fallback['available']) {
                    $value = DB::table($referencedTable)->orderBy($referencedColumn)->value($referencedColumn);
                    if ($value !== null && !$this->isInvalidForeignKeySentinel($value)) {
                        $fallback = ['available' => true, 'value' => $value];
                    }
                }
            } elseif ($rule === 'first_existing') {
                $value = DB::table($referencedTable)->orderBy($referencedColumn)->value($referencedColumn);
                if ($value !== null && !$this->isInvalidForeignKeySentinel($value)) {
                    $fallback = ['available' => true, 'value' => $value];
                }
            } elseif (is_scalar($rule) && $this->referencedValueExistsCached($referencedTable, $referencedColumn, $rule)) {
                $fallback = ['available' => true, 'value' => $rule];
            }
        } catch (Throwable) {
            $fallback = ['available' => false, 'value' => null];
        }

        $this->foreignKeyFallbackValueCache[$reference] = $fallback;

        return $fallback;
    }

    private function referencedValueExistsCached(string $referencedTable, string $referencedColumn, mixed $value): bool
    {
        $cacheKey = $this->referencedValueCacheKey($referencedTable, $referencedColumn, $value);
        if (!array_key_exists($cacheKey, $this->referencedValueExistsCache)) {
            if ($this->isInvalidForeignKeySentinel($value)) {
                $this->referencedValueExistsCache[$cacheKey] = false;
            } else {
                try {
                    $this->referencedValueExistsCache[$cacheKey] = DB::table($referencedTable)
                        ->where($referencedColumn, $value)
                        ->exists();
                } catch (Throwable) {
                    // Fallar abierto evita borrar una referencia si la consulta de validación no pudo ejecutarse.
                    $this->referencedValueExistsCache[$cacheKey] = true;
                }
            }
        }

        return $this->referencedValueExistsCache[$cacheKey];
    }

    /**
     * @return array<string, bool>
     */
    private function referencedValuesExistenceMap(string $referencedTable, string $referencedColumn, array $values): array
    {
        $existence = [];
        $pending = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $cacheKey = $this->referencedValueCacheKey($referencedTable, $referencedColumn, $value);
            if ($this->isInvalidForeignKeySentinel($value)) {
                $this->referencedValueExistsCache[$cacheKey] = false;
            }

            if (array_key_exists($cacheKey, $this->referencedValueExistsCache)) {
                $existence[$cacheKey] = $this->referencedValueExistsCache[$cacheKey];
                continue;
            }

            $pending[$cacheKey] = $value;
        }

        if ($pending !== []) {
            $found = [];
            try {
                foreach (array_chunk($pending, self::BULK_CHUNK_SIZE, true) as $chunk) {
                    $dbValues = DB::table($referencedTable)
                        ->whereIn($referencedColumn, array_values($chunk))
                        ->pluck($referencedColumn);

                    foreach ($dbValues as $dbValue) {
                        if (is_scalar($dbValue)) {
                            $found[$this->referencedValueCacheKey($referencedTable, $referencedColumn, $dbValue)] = true;
                        }
                    }
                }

                foreach ($pending as $cacheKey => $value) {
                    $this->referencedValueExistsCache[$cacheKey] = $found[$cacheKey] ?? false;
                    $existence[$cacheKey] = $this->referencedValueExistsCache[$cacheKey];
                }
            } catch (Throwable) {
                foreach ($pending as $cacheKey => $value) {
                    $this->referencedValueExistsCache[$cacheKey] = true;
                    $existence[$cacheKey] = true;
                }
            }
        }

        return $existence;
    }

    private function referencedValueCacheKey(string $referencedTable, string $referencedColumn, mixed $value): string
    {
        return $referencedTable . "\0" . $referencedColumn . "\0" . (string) $value;
    }

    private function flushBulkInsertRows(string $table, array $rows, int &$errors, bool $normalizeForeignKeys = true): int
    {
        if ($rows === []) {
            return 0;
        }

        $imported = 0;
        foreach (array_chunk($rows, self::BULK_CHUNK_SIZE) as $chunk) {
            $normalized = $this->normalizeRowsForBatch($table, $chunk, $normalizeForeignKeys);
            if ($normalized === []) {
                continue;
            }

            try {
                DB::table($table)->insert($normalized);
                $imported += count($normalized);
            } catch (Throwable $e) {
                Log::warning('SmartImport bulk insert chunk error [' . $table . ']: ' . $e->getMessage());
                foreach ($normalized as $row) {
                    try {
                        DB::table($table)->insert($row);
                        $imported++;
                    } catch (Throwable $rowError) {
                        Log::warning('SmartImport bulk insert fallback row error [' . $table . ']: ' . $rowError->getMessage());
                        $errors++;
                    }
                }
            }
        }

        return $imported;
    }

    private function flushBulkMergeRows(string $table, array $rows, array $keys, int &$errors): int
    {
        if ($rows === [] || $keys === []) {
            return 0;
        }

        $imported = 0;
        foreach (array_chunk($rows, self::BULK_CHUNK_SIZE) as $chunk) {
            foreach ($this->groupRowsByColumnSignature($chunk) as $group) {
                $normalized = $this->normalizeRowsForBatch($table, $group, normalizeForeignKeys: false);
                if ($normalized === []) {
                    continue;
                }

                $updateColumns = array_keys($normalized[0]);
                if ($updateColumns === []) {
                    continue;
                }

                try {
                    DB::table($table)->upsert($normalized, $keys, $updateColumns);
                    $imported += count($normalized);
                } catch (Throwable $e) {
                    Log::warning('SmartImport raw merge chunk error [' . $table . ']: ' . $e->getMessage());
                    foreach ($normalized as $row) {
                        try {
                            DB::table($table)->upsert([$row], $keys, array_keys($row));
                            $imported++;
                        } catch (Throwable $rowError) {
                            Log::warning('SmartImport raw merge fallback row error [' . $table . ']: ' . $rowError->getMessage());
                            $errors++;
                        }
                    }
                }
            }
        }

        return $imported;
    }

    private function flushBulkUpdateRows(string $table, string $pk, array $rows, int &$errors, bool $normalizeForeignKeys = true): int
    {
        if ($rows === []) {
            return 0;
        }

        $imported = 0;
        foreach (array_chunk($rows, self::BULK_CHUNK_SIZE) as $chunk) {
            foreach ($this->groupRowsByColumnSignature($chunk) as $group) {
                $normalized = $this->normalizeRowsForBatch($table, $group, $normalizeForeignKeys);
                if ($normalized === []) {
                    continue;
                }

                $updateColumns = array_values(array_filter(
                    array_keys($normalized[0]),
                    fn (string $column) => $column !== $pk
                ));

                if ($updateColumns === []) {
                    $imported += count($normalized);
                    continue;
                }

                try {
                    DB::table($table)->upsert($normalized, [$pk], $updateColumns);
                    $imported += count($normalized);
                } catch (Throwable $e) {
                    Log::warning('SmartImport bulk update chunk error [' . $table . ']: ' . $e->getMessage());
                    foreach ($normalized as $row) {
                        try {
                            $rowPk = $row[$pk] ?? null;
                            if ($rowPk === null) {
                                $errors++;
                                continue;
                            }

                            $update = $row;
                            unset($update[$pk]);
                            if ($update === []) {
                                $imported++;
                                continue;
                            }

                            DB::table($table)->where($pk, $rowPk)->update($update);
                            $imported++;
                        } catch (Throwable $rowError) {
                            Log::warning('SmartImport bulk update fallback row error [' . $table . ']: ' . $rowError->getMessage());
                            $errors++;
                        }
                    }
                }
            }
        }

        return $imported;
    }

    /**
     * Agrupa filas por conjunto exacto de columnas para evitar que un upsert
     * masivo convierta columnas ausentes en NULL sobre filas existentes.
     *
     * @return array<string, array<int, array>>
     */
    private function groupRowsByColumnSignature(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $columns = array_keys($row);
            sort($columns);
            $signature = implode('|', $columns);
            $grouped[$signature][] = $row;
        }

        return $grouped;
    }

    private function normalizeRowsForBatch(string $table, array $rows, bool $normalizeForeignKeys = true): array
    {
        if ($rows === []) {
            return [];
        }

        $metadata = $this->tableMetadata($table);
        $presentColumns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $presentColumns[$column] = true;
            }
        }

        $orderedColumns = [];
        foreach ($metadata['columns'] as $column) {
            if (isset($presentColumns[$column])) {
                $orderedColumns[] = $column;
            }
        }

        if ($orderedColumns === []) {
            return [];
        }

        $template = array_fill_keys($orderedColumns, null);
        $normalized = array_map(
            static fn (array $row) => array_replace($template, $row),
            $rows
        );

        return $normalizeForeignKeys
            ? $this->normalizeForeignKeysForRows($table, $normalized)
            : $normalized;
    }

    private function reindexDatasetsWithOffsets(array $datasets, array &$rowIndexes): array
    {
        $reindexed = [];
        foreach ($datasets as $table => $rows) {
            foreach ($rows as $row) {
                $rowIndexes[$table] = ($rowIndexes[$table] ?? 0);
                $reindexed[$table][$rowIndexes[$table]] = $row;
                $rowIndexes[$table]++;
            }
        }

        return $reindexed;
    }

    private function mergeImportSummary(array $carry, array $chunk): array
    {
        $carry['imported'] = ($carry['imported'] ?? 0) + ($chunk['imported'] ?? 0);
        $carry['skipped'] = ($carry['skipped'] ?? 0) + ($chunk['skipped'] ?? 0);
        $carry['errors'] = ($carry['errors'] ?? 0) + ($chunk['errors'] ?? 0);

        return $carry;
    }

    /**
     * @return array{
     *     columns: string[],
     *     columns_flip: array<string, bool>,
     *     has_created_at: bool,
     *     has_updated_at: bool,
     *     has_created_by: bool,
     *     has_updated_by: bool
     * }
     */
    private function tableMetadata(string $table): array
    {
        if (!isset($this->tableMetadataCache[$table])) {
            $columns = Schema::getColumnListing($table);
            $columnsFlip = array_fill_keys($columns, true);
            $this->tableMetadataCache[$table] = [
                'columns'        => $columns,
                'columns_flip'   => $columnsFlip,
                'has_created_at' => isset($columnsFlip['created_at']),
                'has_updated_at' => isset($columnsFlip['updated_at']),
                'has_created_by' => isset($columnsFlip['created_by']),
                'has_updated_by' => isset($columnsFlip['updated_by']),
            ];
        }

        return $this->tableMetadataCache[$table];
    }

    private function buildConflictPrompt(string $table, array $item): string
    {
        $incoming = json_encode($item['incoming'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $existing = json_encode($item['existing'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $matched = implode(', ', $item['matched'] ?: ['—']);
        return "Conflicto en tabla `{$table}` (coincide por: {$matched}).\n\n"
            . "REGISTRO EXISTENTE:\n{$existing}\n\n"
            . "REGISTRO ENTRANTE:\n{$incoming}\n\n"
            . "Responde EXACTAMENTE en este formato JSON (sin texto adicional):\n"
            . '{"accion":"omitir|reemplazar|duplicar","razon":"breve explicación en español"}';
    }

    private function parseAIRecommendation(string $texto): array
    {
        if (preg_match('/\{[^{}]*"accion"[^{}]*\}/s', $texto, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data) && isset($data['accion'])) {
                $accion = strtolower($data['accion']);
                if (!in_array($accion, ['omitir', 'reemplazar', 'duplicar'], true)) {
                    $accion = 'omitir';
                }
                return [
                    'accion' => $accion,
                    'razon'  => $data['razon'] ?? 'Sin justificación devuelta por la IA.',
                ];
            }
        }
        return [
            'accion' => 'omitir',
            'razon'  => Str::limit($texto, 280),
        ];
    }

    /**
     * Sincroniza la tabla migrations del target después de un SmartImport.
     *
     * Evita que php artisan migrate falle al intentar crear tablas/columnas
     * que ya existen porque fueron importadas desde el dump.
     *
     * @param array $dumpMigrations Mapa [migration_name => batch] extraído del dump original.
     *                              Si está vacío, se marcan todas las migraciones en disco como ejecutadas.
     * @return array{ synced: int, batch: int }
     */
    public function syncMigrationTable(array $dumpMigrations = []): array
    {
        $dirs = array_merge(
            [database_path('migrations')],
            glob(app_path('Modules/*/*/migrations'), GLOB_ONLYDIR),
        );

        $files = [];
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.php') as $f) {
                $files[] = $f;
            }
        }
        $files = array_unique($files);
        sort($files);

        $disk = [];
        foreach ($files as $path) {
            $name = str_replace('.php', '', basename($path));
            $disk[$name] = true;
        }

        $existingRaw = DB::table('migrations')->pluck('migration')->toArray();

        // Agrupar por nombre normalizado (sin .php)
        $existingByNorm = [];
        foreach ($existingRaw as $name) {
            $norm = str_replace('.php', '', $name);
            $existingByNorm[$norm][] = $name;
        }

        // Separar: limpiar entradas con .php, insertar limpias si no existen
        $toClean = [];
        $pending = [];
        foreach ($disk as $name => $_) {
            $entries = $existingByNorm[$name] ?? [];
            $hasClean = false;
            foreach ($entries as $entry) {
                if (str_ends_with($entry, '.php')) {
                    $toClean[] = $entry;
                } else {
                    $hasClean = true;
                }
            }
            if (!$hasClean) {
                $pending[] = $name;
            }
        }

        if ($toClean !== []) {
            DB::table('migrations')->whereIn('migration', $toClean)->delete();
        }

        $maxBatch = (int) DB::table('migrations')->max('batch');

        $inserts = [];
        foreach ($pending as $name) {
            if ($dumpMigrations !== []) {
                if (isset($dumpMigrations[$name])) {
                    $inserts[] = [
                        'migration' => $name,
                        'batch'     => (int) $dumpMigrations[$name],
                    ];
                }
            } else {
                $maxBatch++;
                $inserts[] = [
                    'migration' => $name,
                    'batch'     => $maxBatch,
                ];
            }
        }

        if ($inserts !== []) {
            DB::table('migrations')->insert($inserts);
            Log::info('SmartImport: Sincronizadas ' . count($inserts) . ' migraciones en la tabla migrations.');
        }

        return [
            'synced'  => count($inserts),
            'cleaned' => count($toClean),
            'batch'   => $maxBatch,
        ];
    }
}
