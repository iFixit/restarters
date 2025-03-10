<template>
  <div>
    <div class="d-flex justify-content-between mb-3">
      <div class="d-flex">
        <h1>{{ $trans.site('devices.fixometer') }}</h1>
        <b-img src="/images/fixometer_doodle.svg" class="ml-4 d-none d-md-block" />
      </div>
      <div>
        <b-btn variant="primary" @click="addData">
          {{ $trans.site('devices.add_data_button') }}
        </b-btn>
      </div>
    </div>
    <hr class="border-top-very-thick" />
    <h2>
      {{ $trans.site('devices.global_impact') }}
    </h2>
    <p>
      <b>{{ $trans.site('devices.huge_impact') }}</b>
      <span v-html="$trans.site('devices.impact_read_more')" />
    </p>
    <AddDataModal ref="addData" />
  </div>
</template>
<script>
import AddDataModal from "./AddDataModal";

export default {
  components: { AddDataModal },
  methods: {
    addData() {
      this.$refs.addData.show()
    }
  }
}
</script>
<style scoped lang="scss">
@import 'resources/global/css/_variables';
@import '~bootstrap/scss/functions';
@import '~bootstrap/scss/variables';
@import '~bootstrap/scss/mixins/_breakpoints';

.border-top-very-thick {
  border-top: 5px solid $black;
}
</style>
