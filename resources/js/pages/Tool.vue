<template>
  <div>
    <Head :title="label" />

    <div class="flex items-center justify-between mb-6">
      <Heading>{{ label }}</Heading>
    </div>

    <Card v-if="loading" class="flex items-center justify-center" style="min-height: 200px">
      <Loader width="60" />
    </Card>

    <Card
      v-else-if="!hasFields"
      class="flex flex-col items-center justify-center p-8 text-center"
      style="min-height: 200px"
    >
      <p class="text-gray-500 dark:text-gray-400">
        {{ __('No settings defined yet.') }}
      </p>
    </Card>

    <form
      v-else
      ref="form"
      autocomplete="off"
      @submit.prevent="submit"
      :data-form-unique-id="formUniqueId"
    >
      <template v-for="panel in panels" :key="`${renderKey}-${panel.attribute || panel.name}`">
        <component
          v-if="panel.fields && panel.fields.length"
          :is="`form-${panel.component}`"
          :panel="panel"
          :name="panel.name"
          :fields="panel.fields"
          :form-unique-id="formUniqueId"
          :validation-errors="validationErrors"
          resource-name="nova-settings"
          :resource-id="null"
          :show-help-text="true"
          mode="form"
          @file-upload-started="uploading = true"
          @file-upload-finished="uploading = false"
          @file-deleted="reload"
        />
      </template>

      <div class="flex items-center justify-end mt-6 space-x-2">
        <Button
          type="submit"
          dusk="nova-settings-save-button"
          :disabled="isWorking || uploading"
          :loading="isWorking"
          :label="__('Save settings')"
        />
      </div>
    </form>
  </div>
</template>

<script>
import { Errors } from 'laravel-nova'
import { Button } from 'laravel-nova-ui'

export default {
  components: { Button },

  props: {
    label: { type: String, default: 'Settings' },
    basePath: { type: String, default: 'nova-settings' },
  },

  provide() {
    return {
      removeFile: this.removeFile,
    }
  },

  data: () => ({
    loading: true,
    isWorking: false,
    uploading: false,
    panels: [],
    fields: [],
    // Bumped on every (re)load so Nova's field components remount: they only
    // attach their fill() method to the field object on mount.
    renderKey: 0,
    validationErrors: new Errors(),
    formUniqueId: `nova-settings-${Math.random().toString(36).slice(2)}`,
  }),

  computed: {
    hasFields() {
      return this.panels.some(panel => panel.fields && panel.fields.length > 0)
    },

    allFields() {
      return this.panels.flatMap(panel => panel.fields || [])
    },
  },

  created() {
    this.load()
  },

  methods: {
    endpoint(path) {
      return `/nova-vendor/nova-settings/${path}`
    },

    async load() {
      this.loading = true

      try {
        const { data } = await Nova.request().get(this.endpoint('fields'))

        this.panels = data.panels
        this.fields = data.fields
        this.renderKey++
      } catch (error) {
        Nova.error(this.__('Unable to load the settings.'))
        throw error
      } finally {
        this.loading = false
      }
    },

    async reload() {
      await this.load()
    },

    formData() {
      const formData = new FormData()

      this.allFields.forEach(field => {
        if (typeof field.fill === 'function') {
          field.fill(formData)
        }
      })

      return formData
    },

    async submit() {
      if (this.isWorking) {
        return
      }

      this.isWorking = true

      try {
        await Nova.request().post(this.endpoint('fields'), this.formData())

        this.validationErrors = new Errors()

        Nova.success(this.__('Settings saved.'))

        await this.load()
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.validationErrors = new Errors(error.response.data.errors)

          Nova.error(this.__('There was a problem submitting the form.'))
        } else {
          Nova.error(this.__('Something went wrong while saving the settings.'))
        }
      } finally {
        this.isWorking = false
      }
    },

    async removeFile(attribute) {
      await Nova.request().delete(this.endpoint(`fields/${attribute}/file`))
    },
  },
}
</script>
