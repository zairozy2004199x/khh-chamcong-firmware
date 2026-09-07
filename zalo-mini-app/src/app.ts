import React from "react";
import { createRoot } from "react-dom/client";
import { App, ZMPRouter, AnimationRoutes, SnackbarProvider, Route } from "zmp-ui";
import HomePage from "./pages/home";
import DanhMucPage from "./pages/danhmuc";
import GioHangPage from "./pages/giohang";
import TinNhanPage from "./pages/tinnhan";
import CaNhanPage from "./pages/canhan";
import DonHangPage from "./pages/donhang";
import BuyPage from "./pages/buy";
import TicketPage from "./pages/ticket";
import "./css/app.css";

const r = (path: string, comp: any) =>
  React.createElement(Route, { path, element: React.createElement(comp) });

const Layout = () =>
  React.createElement(
    App,
    null,
    React.createElement(
      SnackbarProvider,
      null,
      React.createElement(
        ZMPRouter,
        null,
        React.createElement(
          AnimationRoutes,
          null,
          r("/", HomePage),
          r("/danhmuc", DanhMucPage),
          r("/giohang", GioHangPage),
          r("/tinnhan", TinNhanPage),
          r("/canhan", CaNhanPage),
          r("/donhang", DonHangPage),
          React.createElement(Route, { path: "/buy/:id", element: React.createElement(BuyPage) }),
          React.createElement(Route, { path: "/ticket/:maVe", element: React.createElement(TicketPage) })
        )
      )
    )
  );

const root = createRoot(document.getElementById("app")!);
root.render(React.createElement(Layout));
