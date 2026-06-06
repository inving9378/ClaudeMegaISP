<template>
  <q-card class="cpf-card">
    <q-card-section>
      <div class="text-h6">Cambiar mi contraseña</div>
      <div class="text-caption text-grey">Actualiza tu contraseña de acceso al sistema.</div>
    </q-card-section>

    <q-card-section class="q-gutter-y-md">
      <q-input
        v-model="currentPassword"
        type="password"
        label="Contraseña actual"
        outlined dense
        autocomplete="current-password"
        :error="!!errors.current_password"
        :error-message="errors.current_password && errors.current_password[0]"
      />
      <q-input
        v-model="newPassword"
        type="password"
        label="Nueva contraseña"
        outlined dense
        hint="Mínimo 8 caracteres"
        autocomplete="new-password"
        :error="!!errors.new_password"
        :error-message="errors.new_password && errors.new_password[0]"
      />
      <q-input
        v-model="newPasswordConfirmation"
        type="password"
        label="Confirma la nueva contraseña"
        outlined dense
        autocomplete="new-password"
        :error="newPasswordConfirmation !== '' && newPasswordConfirmation !== newPassword"
        error-message="Las contraseñas no coinciden"
      />
    </q-card-section>

    <q-card-actions align="right">
      <q-btn flat label="Cancelar" :disable="saving" @click="resetForm" />
      <q-btn
        color="primary"
        label="Guardar"
        :loading="saving"
        :disable="!canSave"
        @click="submit"
      />
    </q-card-actions>
  </q-card>
</template>

<script>
export default {
  name: 'ChangePasswordForm',
  data() {
    return {
      currentPassword: '',
      newPassword: '',
      newPasswordConfirmation: '',
      saving: false,
      errors: {},
    };
  },
  computed: {
    canSave() {
      return !!this.currentPassword
          && !!this.newPassword
          && this.newPassword === this.newPasswordConfirmation
          && this.newPassword.length >= 8;
    },
  },
  methods: {
    resetForm() {
      this.currentPassword = '';
      this.newPassword = '';
      this.newPasswordConfirmation = '';
      this.errors = {};
    },
    async submit() {
      this.saving = true;
      this.errors = {};
      try {
        const { data } = await axios.post('/profile/change-password', {
          current_password: this.currentPassword,
          new_password: this.newPassword,
          new_password_confirmation: this.newPasswordConfirmation,
        });
        this.notify('positive', data.message || 'Contraseña actualizada correctamente.');
        this.resetForm();
      } catch (e) {
        if (e.response && e.response.status === 422) {
          this.errors = e.response.data.errors || {};
          if (e.response.data.message && Object.keys(this.errors).length === 0) {
            this.notify('negative', e.response.data.message);
          }
        } else {
          this.notify('negative', 'Error al cambiar la contraseña.');
        }
      } finally {
        this.saving = false;
      }
    },
    notify(type, message) {
      if (this.$q && this.$q.notify) {
        this.$q.notify({ type, message, position: 'top-right', timeout: 4000 });
      } else {
        alert(message);
      }
    },
  },
};
</script>

<style scoped>
.cpf-card {
  max-width: 500px;
  margin: 2rem auto;
}
</style>
