<template>
  <NcGuestContent appName="contacts" class="wayf">
    <template #default>
      <div class="wayf-body">
        <div v-if="token !== ''">
          <h2>Providers</h2>
          <p>Where are you from?</p>
          <p>Please tell us your Cloud Provider.</p>
          <div v-if="providers.length !== 0">
            <NcTextField
              v-model="query"
              label="Type to search"
              type="search"
              id="wayf-search"
              name="search"
            >
              <template #icon><Magnify :size="20" /></template>
            </NcTextField>

            <ul id="wayf-list" class="wayf-list">
              <NcListItem
                v-for="p in filtered"
                :key="p.fqdn"
                :href="
                  'https://' +
                  p.fqdn +
                  p.inviteAcceptDialog +
                  '?token=' +
                  token +
                  '&providerDomain=' +
                  providerDomain
                "
                :name="p.name"
                oneLine
              >
                <template #icon>
                  <NcListItemIcon :name="p.name" :subname="p.fqdn">
                    <WeatherCloudyArrowRight :size="20" />
                  </NcListItemIcon>
                </template>
              </NcListItem>
            </ul>
          </div>
          <NcTextField
            v-model="manualProvider"
            label="No providers? No problem! Enter provider manually."
            type="text"
            id="wayf-manual"
            name="manual"
            @keyup.enter="goToManualProvider"
          >
            <template #icon><WeatherCloudyArrowRight :size="20" /></template>
          </NcTextField>
        </div>
        <div v-else>
          <p>You need a token for this feature to work.</p>
        </div>
      </div>
    </template>
  </NcGuestContent>
</template>

<script>
import axios from "@nextcloud/axios";
import Magnify from "vue-material-design-icons/Magnify.vue";
import WeatherCloudyArrowRight from "vue-material-design-icons/WeatherCloudyArrowRight.vue";
import { generateUrl } from "@nextcloud/router";
import {
  NcGuestContent,
  NcListItem,
  NcListItemIcon,
  NcTextField,
} from "@nextcloud/vue";

export default {
  name: "Wayf",
  components: {
    Magnify,
    NcGuestContent,
    NcListItem,
    NcListItemIcon,
    NcTextField,
    WeatherCloudyArrowRight,
  },
  props: {
    providers: { type: Array, default: () => [] },
    providerDomain: { type: String, default: "" },
    token: { type: String, default: "" },
  },
  data: () => ({ query: "" }),
  computed: {
    filtered() {
      const q = this.query.trim().toLowerCase();
      if (!q) return this.providers;
      return this.providers.filter((p) =>
        [p.name, p.fqdn].some((v) => String(v).toLowerCase().includes(q)),
      );
    },
  },

  methods: {
    async discoverProvider(base) {
      const resp = await axios.get(generateUrl("/apps/contacts/ocm/discover"), {
        params: { base },
        timeout: 8000,
      });
      if (!resp.data?.inviteAcceptDialogAbsolute)
        throw new Error("Discovery failed");

      // append provider & token safely
      const u = new URL(resp.data.inviteAcceptDialogAbsolute);
      if (this.provider) u.searchParams.set("provider", this.provider);
      if (this.token) u.searchParams.set("token", this.token);
      return u.toString();
    },
    async goToManualProvider() {
      const input = (this.manualProvider || "").trim();
      if (!input) return;
      try {
        const target = await this.discoverProvider(input);
        window.location.href = target;
      } catch (e) {
        // TODO: handle error and show error dialog to user
        console.error(e);
      }
    },
  },
};
</script>
