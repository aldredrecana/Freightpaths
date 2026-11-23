import { Ionicons } from "@expo/vector-icons";
import { Tabs } from "expo-router";
import { StyleSheet, View } from "react-native";
import { colors } from "../../theme";
import { TripsProvider } from "../../trips";

type IconName = keyof typeof Ionicons.glyphMap;

// The icon sits in a soft green pill while its tab is selected.
function TabIcon({ name, focused }: { name: IconName; focused: boolean }) {
  return (
    <View style={[styles.pill, focused && styles.pillOn]}>
      <Ionicons
        name={name}
        size={22}
        color={focused ? colors.brand : colors.muted}
      />
    </View>
  );
}

export default function TabsLayout() {
  return (
    <TripsProvider>
      <Tabs
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.brand,
          tabBarInactiveTintColor: colors.muted,
          tabBarLabelStyle: { fontSize: 12, fontWeight: "700" },
          tabBarStyle: { backgroundColor: "#fff", borderTopColor: colors.line },
        }}
      >
        <Tabs.Screen
          name="index"
          options={{
            title: "Home",
            tabBarIcon: ({ focused }) => (
              <TabIcon name="home" focused={focused} />
            ),
          }}
        />
        <Tabs.Screen
          name="schedule"
          options={{
            title: "Schedule",
            tabBarIcon: ({ focused }) => (
              <TabIcon name="calendar-outline" focused={focused} />
            ),
          }}
        />
        <Tabs.Screen
          name="history"
          options={{
            title: "History",
            tabBarIcon: ({ focused }) => (
              <TabIcon name="time-outline" focused={focused} />
            ),
          }}
        />
      </Tabs>
      <Tabs.Screen
        name="profile"
        options={{
          title: "Profile",
          tabBarIcon: ({ focused }) => (
            <TabIcon name="person-circle-outline" focused={focused} />
          ),
        }}
      />
    </TripsProvider>
  );
}

const styles = StyleSheet.create({
  pill: {
    width: 52,
    height: 30,
    borderRadius: 15,
    alignItems: "center",
    justifyContent: "center",
  },
  pillOn: { backgroundColor: colors.brandSoft },
});
