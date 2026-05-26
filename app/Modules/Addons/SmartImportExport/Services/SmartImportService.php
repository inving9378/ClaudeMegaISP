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

    public function setDumpColumns(array $dumpColumns): void
    {
        $this->dumpColumnsByTable = $dumpColumns;
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

        // Reset por-llamada — parseSql lo llena
        $this->lastRowCounts = [];

        $format = $this->detectFormat($extension, $path);
        $datasets = match ($format) {
            'sql'   => $this->parseSql($path),
            'json'  => $this->parseJson($path),
            'csv'   => $this->parseCsv($path),
            'xlsx'  => $this->parseSpreadsheet($path),
            'zip'   => $this->parseZip($path),
            default => [],
        };

        $report = [];
        foreach ($datasets as $table => $rows) {
            $rows = array_values($rows);
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            // Count exacto del archivo (puede ser > count($rows) si truncamos en RAM)
            $exactCount = $this->lastRowCounts[$table] ?? count($rows);
            $report[] = [
                'table'     => $table,
                'module'    => $info['module'] ?? 'Sin clasificar',
                'model'     => $info['model'] ?? null,
                'records'   => $exactCount,
                'sample'    => array_slice($rows, 0, 3),
                'known'     => $info !== null,
                'truncated' => $exactCount > count($rows),
            ];
        }

        $totalRows = !empty($this->lastRowCounts)
            ? array_sum($this->lastRowCounts)
            : array_sum(array_map('count', $datasets));

        return [
            'token'        => $token,
            'file'         => $storedName,
            'format'       => $format,
            'datasets'     => $datasets,
            'dump_columns' => $this->dumpColumnsByTable, // pobladas por parseSql → handle schema drift
            'report'       => $this->sanitizeUtf8($report),
            'total_rows'   => $totalRows,
        ];
    }

    public function detectConflicts(array $datasets): array
    {
        $conflicts = [];
        foreach ($datasets as $table => $rows) {
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            if (!$info || !Schema::hasTable($table)) {
                continue;
            }
            $keys = $info['conflict_keys'] ?? [];
            $tableConflicts = [];

            foreach ($rows as $index => $row) {
                $existing = $this->findExisting($table, $keys, $row);
                if ($existing) {
                    $tableConflicts[] = [
                        'index'    => $index,
                        'incoming' => $row,
                        'existing' => $existing,
                        'matched'  => $this->matchedKeys($keys, $row, $existing),
                    ];
                }
            }

            if (!empty($tableConflicts)) {
                $conflicts[$table] = $tableConflicts;
            }
        }
        return $conflicts;
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
            $info = self::TABLE_MODULE_MAP[$table] ?? null;
            $skipReason = $this->validateMapEntry($table, $info);
            if ($skipReason !== null) {
                $summary[$table] = ['imported' => 0, 'skipped' => count($rows), 'errors' => 0, 'reason' => $skipReason];
                continue;
            }

            $defaultAction = $options[$table]['action'] ?? 'skip';
            $perRow = $options[$table]['conflicts'] ?? [];
            $pk = $this->pkOf($table, $info);
            $imported = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($rows as $index => $row) {
                try {
                    $existing = $this->findExisting($table, $info['conflict_keys'] ?? [], $row);
                    $action = $existing ? ($perRow[$index] ?? $defaultAction) : 'insert';

                    if ($action === 'skip') {
                        $skipped++;
                        continue;
                    }
                    if ($action === 'replace' && $existing) {
                        $this->updateRow($table, $info, $existing[$pk], $row);
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

    public function cleanup(string $storedName): void
    {
        $path = storage_path(self::STORAGE_DIR . '/' . $storedName);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /* ===================== Helpers privados ===================== */

    private function detectFormat(string $extension, string $path): string
    {
        $byExt = ['sql' => 'sql', 'json' => 'json', 'csv' => 'csv', 'xlsx' => 'xlsx', 'xls' => 'xlsx', 'zip' => 'zip'];
        if (isset($byExt[$extension])) {
            return $byExt[$extension];
        }
        $head = @file_get_contents($path, false, null, 0, 512) ?: '';
        if (str_starts_with($head, "PK\x03\x04")) return 'zip';
        if (Str::startsWith(ltrim($head), ['{', '['])) return 'json';
        if (stripos($head, 'INSERT INTO') !== false || stripos($head, 'CREATE TABLE') !== false) return 'sql';
        return 'csv';
    }

    private function parseZip(string $path): array
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
                $sub = match ($ext) {
                    'sql'         => $this->parseSql($extracted->getPathname()),
                    'json'        => $this->parseJson($extracted->getPathname()),
                    'csv'         => $this->parseCsv($extracted->getPathname()),
                    'xlsx', 'xls' => $this->parseSpreadsheet($extracted->getPathname()),
                    default       => [],
                };
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

    private function parseSql(string $path): array
    {
        // Pre-pass: extraer orden de columnas desde CREATE TABLE statements.
        // Necesario para schema-drift-safe array_combine en sanitizeRow.
        // Se acumula porque parseSql puede llamarse múltiples veces (parseZip
        // itera archivos del zip).
        foreach ($this->extractDumpColumns($path) as $t => $cols) {
            $this->dumpColumnsByTable[$t] = $cols;
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
        $header         = '';     // buffer mientras buscamos "INSERT INTO ... VALUES"
        $tuple          = '';     // buffer de la tupla actual cuando estamos dentro de VALUES
        $depth          = 0;      // profundidad de paréntesis (siempre 0 o 1 en SQL bien formado)
        $inString       = false;  // dentro de string '...'
        $escape         = false;  // próximo char está escapado
        $scanned        = 0;      // bytes totales escaneados (para MAX_SCAN_BYTES)

        try {
            while (!feof($handle) && $scanned < self::MAX_SCAN_BYTES) {
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
                                $values = $this->parseSqlTuple($tuple);
                                $accept = empty($currentColumns)
                                    ? !empty($values)
                                    : (count($values) === count($currentColumns));
                                if ($accept) {
                                    $rowCounts[$currentTable] = ($rowCounts[$currentTable] ?? 0) + 1;
                                    if ($rowCounts[$currentTable] <= self::MAX_ROWS_PER_TABLE) {
                                        $datasets[$currentTable][] = empty($currentColumns)
                                            ? $values
                                            : array_combine($currentColumns, $values);
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
        while ($i < $len) {
            $c = $tuple[$i];
            if ($inString) {
                if ($c === '\\' && $i + 1 < $len) { $current .= $tuple[$i + 1]; $i += 2; continue; }
                if ($c === "'") { $inString = false; $i++; continue; }
                $current .= $c; $i++; continue;
            }
            if ($c === "'") { $inString = true; $i++; continue; }
            if ($c === ',') { $values[] = $this->castSqlValue(trim($current)); $current = ''; $i++; continue; }
            $current .= $c; $i++;
        }
        if ($current !== '' || $tuple !== '') {
            $values[] = $this->castSqlValue(trim($current));
        }
        return $values;
    }

    private function castSqlValue(string $raw)
    {
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
            $tableKey = isset(self::TABLE_MODULE_MAP[$tableName]) ? $tableName : ($this->guessTableFromHeader($header) ?? $tableName);
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
        foreach (self::TABLE_MODULE_MAP as $table => $info) {
            $keys = $info['conflict_keys'] ?? [];
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
            if (!empty($row[$key]) && Schema::hasColumn($table, $key)) {
                $query->orWhere($key, $row[$key]);
                $hasFilter = true;
            }
        }
        if (!$hasFilter) return null;
        $found = $query->first();
        return $found ? (array) $found : null;
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
        $destColumns = Schema::getColumnListing($table);
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
        return array_intersect_key($row, array_flip($destColumns));
    }

    /**
     * Pre-pass del archivo SQL para extraer el orden de columnas de cada
     * tabla desde sus CREATE TABLE statements. Streaming-friendly: lee en
     * chunks de 64KB y mantiene un buffer rotativo de 256KB (suficiente
     * para CREATE TABLEs gigantes).
     *
     * Regex es deliberadamente tolerante: matchea `CREATE TABLE \`name\` (...)`
     * seguido de `ENGINE=` o `TYPE=` (mysqldump emite uno u otro). Dentro
     * del cuerpo, columnas son líneas que arrancan con backtick — saltamos
     * `PRIMARY KEY`, `KEY`, `CONSTRAINT`, `INDEX`, etc. porque NO arrancan
     * con backtick.
     *
     * @return array<string, string[]>
     */
    private function extractDumpColumns(string $path): array
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
                // Procesar todos los CREATE TABLE completos que estén en el buffer
                while (preg_match(
                    '/CREATE\s+TABLE\s+`?(\w+)`?\s*\((.*?)\)\s*(?:ENGINE|TYPE)\s*=/is',
                    $buf,
                    $m,
                    PREG_OFFSET_CAPTURE
                )) {
                    $name = $m[1][0];
                    $body = $m[2][0];
                    // Columnas: líneas que arrancan con backtick + identificador
                    // (filtra KEY/PRIMARY KEY/CONSTRAINT/etc que no arrancan con `)
                    if (preg_match_all('/^\s*`(\w+)`\s+\w/m', $body, $colM)) {
                        if (!empty($colM[1])) {
                            $result[$name] = $colM[1];
                        }
                    }
                    // Avanzar buffer past este match
                    $endPos = $m[0][1] + strlen($m[0][0]);
                    $buf = substr($buf, $endPos);
                }
                // Conservar últimos 256KB para no perder CREATE TABLE que cruzan chunks
                if (strlen($buf) > 256 * 1024) {
                    $buf = substr($buf, -256 * 1024);
                }
            }
        } finally {
            fclose($handle);
        }
        return $result;
    }

    /**
     * Valida que la entrada del map sea ejecutable. Devuelve null si OK,
     * o un string con la razón del skip ('tabla_no_mapeada', etc.).
     */
    private function validateMapEntry(string $table, ?array $info): ?string
    {
        if (!$info) {
            return 'tabla_no_mapeada';
        }
        $mode = $info['mode'] ?? 'model';
        if ($mode === 'model' && (empty($info['model']) || !class_exists($info['model']))) {
            return 'modelo_no_encontrado';
        }
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
            $modelClass::create($this->sanitizeRow($row, $table));
            return;
        }
        $clean = $this->sanitizeRow($row, $table);
        $clean = $this->injectAuditColumns($table, $clean, isUpdate: false);
        DB::table($table)->insert($clean);
    }

    /** Update ramificado por modo. */
    private function updateRow(string $table, array $info, mixed $existingId, array $row): void
    {
        if (($info['mode'] ?? 'model') === 'model') {
            $modelClass = $info['model'];
            $modelClass::query()->whereKey($existingId)
                ->update($this->sanitizeRow($row, $table));
            return;
        }
        $clean = $this->sanitizeRow($row, $table);
        $clean = $this->injectAuditColumns($table, $clean, isUpdate: true);
        DB::table($table)->where($this->pkOf($table, $info), $existingId)->update($clean);
    }

    /**
     * Replica el auto-stamp de BaseModel (created_by/updated_by) y los
     * timestamps de Eloquent para tablas que no pasan por un modelo Eloquent.
     */
    private function injectAuditColumns(string $table, array $row, bool $isUpdate): array
    {
        $columns = Schema::getColumnListing($table);
        $userId = auth()->id();
        $now = now();
        if (in_array('updated_at', $columns, true)) {
            $row['updated_at'] = $now;
        }
        if (in_array('updated_by', $columns, true) && $userId) {
            $row['updated_by'] = $userId;
        }
        if (!$isUpdate) {
            if (in_array('created_at', $columns, true) && !isset($row['created_at'])) {
                $row['created_at'] = $now;
            }
            if (in_array('created_by', $columns, true) && $userId && !isset($row['created_by'])) {
                $row['created_by'] = $userId;
            }
        }
        return $row;
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
}
